<?php

namespace App\Domain\Catalog;

use App\Domain\Catalog\Video\FfmpegVideoProbe;
use App\Domain\Catalog\Video\VideoProbe;
use Illuminate\Support\ServiceProvider;

class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(VideoProbe::class, FfmpegVideoProbe::class);
    }
}
