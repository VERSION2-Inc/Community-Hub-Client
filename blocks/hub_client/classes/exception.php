<?php
/**
 *  MAJ Hub Client
 *  
 *  @author  VERSION2, Inc.
 *  @version $Id: exception.php 152 2012-12-02 07:04:43Z malu $
 */
namespace hub_client;

/**
 *  MAJ Hub Client exception
 */
class exception extends \moodle_exception
{
    /**
     *  Constructor
     *  
     *  @param string $code  The error string ID without prefix "error:"
     *  @param mixed  $a     (Optional) Additional parameter
     */
    public function __construct($code, $a = null)
    {
        parent::__construct("error:$code", 'block_hub_client', '', $a);
    }

    /**
     *  Jsonify
     *  
     *  @global object $CFG
     *  @param \Exception $ex
     *  @return string
     */
    public static function jsonify(\Exception $ex)
    {
        global $CFG;

        $json = array('message' => $ex->getMessage());
        if (!empty($CFG->debug) and $CFG->debug >= DEBUG_DEVELOPER) {
            $json += array(
                'file'  => substr($ex->getFile(), strlen($CFG->dirroot)),
                'line'  => $ex->getLine(),
                'trace' => \format_backtrace($ex->getTrace(), true),
                );
        }
        return json_encode($json);
    }
}
