<?php

if (!interface_exists('React\EventLoop\TimerInterface') && interface_exists('React\EventLoop\Timer\TimerInterface')) {
    class_alias('React\EventLoop\Timer\TimerInterface', 'React\EventLoop\TimerInterface');
}
