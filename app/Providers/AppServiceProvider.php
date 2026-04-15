<?php

namespace App\Providers;

use App\Models\Flavor;
use App\Observers\FlavorObserver;
use Illuminate\Hashing\BcryptHasher;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Node.js bcryptjs uses $2b$ prefix; PHP expects $2y$.
        // They are functionally identical — just normalise on check.
        $this->app->extend('hash', function ($manager) {
            $manager->extend('bcrypt', function () {
                return new class extends BcryptHasher {
                    public function check($value, $hashedValue, array $options = [])
                    {
                        if (str_starts_with($hashedValue, '$2b$')) {
                            $hashedValue = '$2y$' . substr($hashedValue, 4);
                        }
                        return parent::check($value, $hashedValue, $options);
                    }
                };
            });
            return $manager;
        });
    }

    public function boot(): void
    {
        Flavor::observe(FlavorObserver::class);
    }
}
