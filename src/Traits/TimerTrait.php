<?php

namespace Charcoal\Conductor\Traits;

use Charcoal\Conductor\Model\Timer;

trait TimerTrait
{
    private Timer $timer;

    public function __construct()
    {
        $this->timer = new Timer();
        parent::__construct();
    }

    public function timer(): Timer
    {
        return $this->timer;
    }
}
