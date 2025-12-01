<?php

use App\Mcp\Servers\ObsvrServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('obsvr', ObsvrServer::class);
