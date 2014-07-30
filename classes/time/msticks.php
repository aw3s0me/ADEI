<?php

ADEI::RequireClass("time/unixticks");

    // .NET timestamp (The number of 100-nanosecond intervals that have elapsed since 12:00:00 midnight, January 1, 0001)
class MSTICKS extends UNIXTicks {
 function __construct(READER $reader = NULL, $opts = 0) {
    if (!is_array($opts)) $opts = array("correction" => $opts);
    $opts['offset'] = 621355968000000000;
    $opts['ticks_per_second'] = 10000000;
    parent::__construct($reader, $opts);
 }
}

?>