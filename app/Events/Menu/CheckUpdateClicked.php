<?php

namespace App\Events\Menu;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CheckUpdateClicked
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public array $item,
        public array $combo = []
    )
    {
        //
    }
}
