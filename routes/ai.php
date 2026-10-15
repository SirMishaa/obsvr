<?php

declare(strict_types=1);

use App\Mcp\Servers\ObsvrServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('obsvr', ObsvrServer::class);
