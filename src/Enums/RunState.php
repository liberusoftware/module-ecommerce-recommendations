<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Enums;

enum RunState: string
{
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
