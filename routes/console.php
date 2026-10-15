<?php

use Illuminate\Support\Facades\Schedule;

// Borra a diario los tokens de Sanctum vencidos hace más de 24 horas.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
