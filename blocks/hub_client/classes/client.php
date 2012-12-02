<?php
/**
 *  MAJ Hub Client
 *  
 *  @author  VERSION2, Inc.
 *  @version $Id: client.php 152 2012-12-02 07:04:43Z malu $
 */
namespace hub_client;

require_once __DIR__.'/exception.php';
require_once __DIR__.'/scoped.php';

/**
 *  Web Client for Moodle
 */
class client
{
    /** @const string */
    const USER_AGENT = 'MAJ Hub Client/2.3 dev';

    /** @const int */
    const TIMEOUT = 60;

    /** @var string */
    public $wwwroot;

    /** @var \Zend\Http\Client */
    private $client;

    /**
     *  Constructor
     *  
     *  @global object $CFG
     *  @param string $wwwroot  The wwwroot of the remote MAJ Hub server
     *  @throws \moodle_exception
     */
    public function __construct($wwwroot)
    {
        global $CFG;

        self::construct_zend_autoloader();

        $this->wwwroot = rtrim($wwwroot, '/');
        $this->client = new \Zend\Http\Client(null,
            array('useragent' => self::USER_AGENT, 'timeout' => self::TIMEOUT)
            );
        if (!empty($CFG->proxyhost) && !\is_proxybypass($this->wwwroot)) {
            if (!empty($CFG->proxytype) && $CFG->proxytype == 'SOCKS5') {
                // TODO: CURLPROXY_SOCKS5
                throw new \moodle_exception('error_proxy_socks5_not_supported');
            }
            $adapter = new \Zend\Http\Client\Adapter\Proxy();
            $adapter->setOptions(array('proxy_host' => $CFG->proxyhost));
            if (!empty($CFG->proxyport)) {
                $adapter->setOptions(array('proxy_port' => $CFG->proxyport));
            }
            if (!empty($CFG->proxyuser) && !empty($CFG->proxypassword)) {
                $adapter->setOptions(array(
                    'proxy_user' => $CFG->proxyuser,
                    'proxy_pass' => $CFG->proxypassword,
                    ));
            }
            $this->client->setAdapter($adapter);
        }
    }

    /**
     *  Gets the MAJ Hub site title
     *  
     *  @return string
     *  @throws exception
     */
    public function get_title()
    {
        try {
            $response = $this->get('/local/majhub/api/index.php');
            $xml = @simplexml_load_string($response->getBody());
            if (!$xml || !$xml['title'])
                throw new \RuntimeException();
            return (string)$xml['title'];
        } catch (\Exception $ex) {
            throw new exception('notfound');
        }
    }

    /**
     *  Logs in to the MAJ Hub
     *  
     *  @param string $username
     *  @param string $password
     *  @throws exception
     */
    public function login($username, $password)
    {
        try {
            $this->client->addCookie($this->get('/login/index.php')->getCookie());
            $response = $this->post('/login/index.php', compact('username', 'password'));
            if (preg_match('/<form[^>]* id="login"/', $response->getBody()))
                throw new \RuntimeException();
        } catch (\Exception $ex) {
            throw new exception('loginfailed');
        }
    }

    /**
     *  Creates a new courseware in the MAJ Hub
     *  
     *  @param string $fullname   The full name of the course
     *  @param string $shortname  The short name of the course
     *  @param string $filesize   The complete size of the course backup file in bytes
     *  @return int  An id of a courseware created
     *  @throws exception
     */
    public function create($fullname, $shortname, $filesize)
    {
        try {
            $response = $this->post('/local/majhub/api/create.php',
                compact('fullname', 'shortname', 'filesize')
                );
            $xml = @simplexml_load_string($response->getBody());
            if (!$xml || !$xml->courseware || !$xml->courseware['id'])
                throw new \RuntimeException();
            return (int)(string)$xml->courseware['id'];
        } catch (\Exception $ex) {
            throw new exception('createfailed');
        }
    }

    /**
     *  Uploads a part of a course backup file to the MAJ Hub courseware
     *  
     *  @param int $coursewareid  The target courseware id
     *  @param string $position   The chunk position on the backup file
     *  @param string $content    The chunk data of the backup file
     *  @return int  A part id of the courseware file parts
     *  @throws exception
     */
    public function upload($coursewareid, $position, $content)
    {
        try {
            $filename = 'coursebackup.mbz';
            $response = $this->post('/local/majhub/api/upload.php',
                array('courseware' => $coursewareid, 'position' => $position),
                array('content' => array($filename => $content))
                );
            $xml = @simplexml_load_string($response->getBody());
            if (!$xml || !$xml->part || !$xml->part['id'])
                throw new \RuntimeException();
            return (int)(string)$xml->part['id'];
        } catch (\Exception $ex) {
            throw new exception('uploadfailed');
        }
    }

    /**
     *  Requests with GET method
     *  
     *  @param string $path
     *  @return \Zend\Http\Response
     *  @throws \Zend\Http\Exception\RuntimeException
     */
    protected function get($path)
    {
        $this->clearParameters();
        $this->client->setMethod(\Zend\Http\Request::METHOD_GET);
        $this->client->setUri($this->wwwroot . $path);
        return $this->send();
    }

    /**
     *  Requests with POST method
     *  
     *  @param string $path
     *  @param array $params
     *  @param array $files   array($formname => array($filename => $content), ...)
     *  @return \Zend\Http\Response
     *  @throws \Zend\Http\Exception\RuntimeException
     */
    protected function post($path, array $params = array(), array $files = array())
    {
        $this->clearParameters();
        $this->client->setMethod(\Zend\Http\Request::METHOD_POST);
        $this->client->setUri($this->wwwroot . $path);
        $this->client->setParameterPost($params);
        if (!empty($files)) {
            $mimetype = 'application/octet-stream';
            foreach ($files as $formname => $pair) {
                if (!is_array($pair))
                    throw new \InvalidArgumentException();
                list ($filename, $content) = each($pair);
                $this->client->setFileUpload($filename, $formname, $content, $mimetype);
            }
        }
        return $this->send();
    }

    /**
     *  Clears the Request parameters
     *  
     *  @throws \Zend\Http\Exception\RuntimeException
     */
    protected function clearParameters()
    {
        $this->client->setParameterPost(array());
        $this->client->getRequest()->getFiles()->fromArray(array());
    }

    /**
     *  Shortcut to \Zend\Http\Client->send() with the workaround for 'arg_separator.output'
     *  
     *  @return \Zend\Http\Response
     *  @throws \Zend\Http\Exception\RuntimeException
     */
    protected function send()
    {
        // Zend\Http\Client uses http_build_query(), that depends on arg_separator.output
        $separator = ini_get('arg_separator.output');
        $scoped = new scoped(function () use ($separator)
        {
            ini_set('arg_separator.output', $separator);
        });
        ini_set('arg_separator.output', '&');

        return $this->client->send();
    }

    /**
     *  Constructs a Zend Autoloader
     */
    protected static function construct_zend_autoloader()
    {
        static $loader = null;
        if ($loader === null) {
            require_once __DIR__.'/../lib/Zend/Loader/StandardAutoloader.php';
            $loader = new \Zend\Loader\StandardAutoloader();
            $loader->registerNamespace('Zend', dirname(__DIR__).'/lib/Zend')->register();
        }
    }
}
