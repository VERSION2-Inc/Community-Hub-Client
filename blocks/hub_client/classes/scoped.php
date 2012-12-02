<?php
/**
 *  MAJ Hub Client
 *  
 *  @author  VERSION2, Inc.
 *  @version $Id: scoped.php 152 2012-12-02 07:04:43Z malu $
 */
namespace hub_client;

/**
 *  Scoped closure
 */
class scoped
{
    /** @var callable */
    private $callback;

    /**
     *  Constructor
     *  
     *  @param callable $callback
     */
    public function __construct(/*callable*/ $callback)
    {
        $this->callback = $callback;
    }

    /**
     *  Destructor
     */
    public function __destruct()
    {
        call_user_func($this->callback);
    }
}
