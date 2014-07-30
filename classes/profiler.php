<?php

/*
require_once("Benchmark/Profiler.php");


function __profile__($cmd) {
        static $log, $last_time, $total;
	
        list($usec, $sec) = explode(" ", microtime());
        $now = (float) $usec + (float) $sec;
	
        if($cmd) {
                if($cmd == 'get') {
                        unregister_tick_function('__profile__');
                        foreach($log as $function => $time) {
                                if($function != '__profile__') {
                                        $by_function[$function] = round($time / $total * 100, 2);
                                }
                        }
                        arsort($by_function);
                        return $by_function;
                }
                else if($cmd == 'init') {
                        $last_time = $now;
                        return;
                }
		else if($cmd == 'restart') {
			register_tick_function('__profile__', $this);
			return;
		}
        }
        $delta = $now - $last_time;
        $last_time = $now;
        $trace = debug_backtrace();
        $caller = $trace[1]['function'];
        @$log[$caller] += $delta;
        $total += $delta;
}

class PROFILER {
 var $profiler;
 
 function __construct() {
    $this->Start();
 }
 
 function __destruct() {
    $this->Display(false);
 }
 
 function Start() {
    global $profiler;
    
    __profile__('init');
    register_tick_function('__profile__', $this);
    declare(ticks=1); 


    $this->profiler = new Benchmark_Profiler(true);
    $this->profiler->start();
 }


 function Display($prof = true) {
    if ($prof) {
	$this->profiler->stop();
	$this->profiler->display();
    }

    print_r(__profile__('get'));
    echo("\n");
 }

 function Restart() {
    $this->profiler->start();
    __profile__('restart');
 }


 function EnterSection($name) {
    $this->profiler->enterSection($name);
 }

 function LeaveSection($name) {
    $this->profiler->leaveSection($name);
 }
}

//$adei_profiler = new PROFILER();
*/

?>