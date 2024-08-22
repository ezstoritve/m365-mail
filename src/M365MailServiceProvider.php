<?php
namespace EZStoritve\M365Mail;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Mail;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

use EZStoritve\M365Mail\Services\MicrosoftGraphService;

class M365MailServiceProvider extends PackageServiceProvider
{

    public function configurePackage(Package $package): void
    {
        $package->name('m365-mail');
    }

    public function boot()
    {
        Mail::extend('m365-mail', function (array $config): M365Transport {
            return new M365Transport(new MicrosoftGraphService(
                tenantId: $config['tenant_id'],
                clientId: $config['client_id'],
                clientSecret: $config['client_secret'],
                mailFromAddress: $config['from_address'],
                mailFromName: $config['from_name']
            ));
        });
    }

    public function register()
    {
    }
}
