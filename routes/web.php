<?php

use Illuminate\Support\Facades\Route;

Route::get('/', 'DashboardController')->name('dashboard');
Route::get('/api/metrics', 'MetricsController')->name('api.metrics');
Route::post('/api/insights', 'AiInsightController')->name('api.insights');
