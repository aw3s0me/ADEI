<?php

class AUXApplication
{
    var $command;

    function __construct($command)
    {
        $this->command = $command;
    }

    function Run($args = array())
    {
        $spec = array(1 => array("pipe", "w"));

        $command = $this->command;

        foreach ($args as $arg) {
            $command .= " " . escapeshellcmd($arg);
        }

        $process = proc_open($command, $spec, $pipes);

        echo stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        return proc_close($process);
    }
}

class AUXApplicationParameters
{
    var $command;
    var $parameters;

    function __construct($command)
    {
        $this->command = $command;
        $this->parameters = array();
    }

    function Run($args = array())
    {
        $spec = array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w")
        );

        $command = $this->command;

        foreach ($args as $arg) {
            $command .= " " . escapeshellcmd($arg);
        }
	
        $process = proc_open($command, $spec, $pipes);

        foreach ($this->parameters as $name => $value) {
            fwrite($pipes[0], "$name::$value\n");
        }
        fclose($pipes[0]);

        echo stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        return proc_close($process);
    }

    function AddParameter($name, $value)
    {
        $this->parameters[$name] = $value;
    }

    function ClearParameters()
    {
        $this->parameters = array();
    }
}
?>