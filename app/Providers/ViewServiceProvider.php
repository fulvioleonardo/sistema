<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class ViewServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        view()->composer(
            'tenant.layouts.partials.header',
            \App\Http\ViewComposers\Tenant\CompanyViewComposer::class
        );

        view()->composer(
            'tenant.layouts.partials.sidebar',
            \App\Http\ViewComposers\Tenant\CompanyViewComposer::class
        );

        view()->composer(
            'tenant.layouts.partials.header',
            \App\Http\ViewComposers\Tenant\UserViewComposer::class
        );

        view()->composer(
            'tenant.layouts.partials.header',
            'Modules\Document\Http\ViewComposers\DocumentViewComposer'
        );

        view()->composer(
            'tenant.layouts.partials.header',
            \App\Http\ViewComposers\Tenant\ModuleViewComposer::class
        );

        view()->composer(
            'tenant.layouts.partials.sidebar',
            \App\Http\ViewComposers\Tenant\CompanyViewComposer::class
        );

        view()->composer(
            'tenant.layouts.partials.sidebar',
            \App\Http\ViewComposers\Tenant\ModuleViewComposer::class
        );

        view()->composer(
            'tenant.layouts.partials.sidebar',
            'Modules\BusinessTurn\Http\ViewComposers\BusinessTurnViewComposer'
        );

        view()->composer(
            'tenant.layouts.app',
            \App\Http\ViewComposers\Tenant\CompactSidebarViewComposer::class
        );
        view()->composer(
            'tenant.layouts.app_pos',
            \App\Http\ViewComposers\Tenant\CompactSidebarViewComposer::class
        );

        //Ecommerce

        /*view()->composer(
            'ecommerce::layouts.partials_ecommerce.header',
            'Modules\Ecommerce\Http\ViewComposers\InformationContactViewComposer'
        );*/

       /* view()->composer(
            'tenant.layouts.partials_ecommerce.header_options',
            'App\Http\ViewComposers\Tenant\CompanyViewComposer'
        );*/

        view()->composer(
            'ecommerce::layouts.partials_ecommerce.featured_products',
            'Modules\Ecommerce\Http\ViewComposers\FeaturedProductsViewComposer'
        );
        view()->composer(
            'ecommerce::layouts.partials_ecommerce.featured_products_bottom',
            'Modules\Ecommerce\Http\ViewComposers\FeaturedProductsViewComposer'
        );
        view()->composer(
            'ecommerce::layouts.partials_ecommerce.widget_products',
            'Modules\Ecommerce\Http\ViewComposers\FeaturedProductsViewComposer'
        );
        view()->composer(
            'ecommerce::layouts.partials_ecommerce.list_products',
            'Modules\Ecommerce\Http\ViewComposers\FeaturedProductsViewComposer'
        );
        view()->composer(
            'ecommerce::layouts.partials_ecommerce.sidemenu',
            'Modules\Ecommerce\Http\ViewComposers\MenuViewComposer'
        );
        view()->composer(
            'ecommerce::layouts.partials_ecommerce.header_bottom_sticky',
            'Modules\Ecommerce\Http\ViewComposers\MenuViewComposer'
        );
        view()->composer(
            'ecommerce::layouts.partials_ecommerce.home_slider',
            'Modules\Ecommerce\Http\ViewComposers\PromotionsViewComposer'
        );
        view()->composer(
            ['ecommerce::layouts.partials_ecommerce.footer', 'ecommerce::layouts.partials_ecommerce.header', 'ecommerce::cart.detail', 'ecommerce::layouts.partials_ecommerce.sidebar_product_right', 'ecommerce::layouts.partials_ecommerce.mobile_menu'],
            'Modules\Ecommerce\Http\ViewComposers\InformationContactViewComposer'
        );
        view()->composer(
            'ecommerce::layouts.partials_ecommerce.mobile_menu',
            'Modules\Ecommerce\Http\ViewComposers\MenuViewComposer'
        );


        view()->composer(
            'tenant.layouts.partials.sidebar',
            'Modules\LevelAccess\Http\ViewComposers\ModuleLevelViewComposer'
        );

        view()->composer(
            'tenant.layouts.partials.sidebar_styles',
            \App\Http\ViewComposers\Tenant\ConfigurationVisualViewComposer::class
        );

        view()->composer(
            'tenant.layouts.app',
            \App\Http\ViewComposers\Tenant\ConfigurationVisualViewComposer::class
        );

        view()->composer(
            'tenant.layouts.auth',
            \App\Http\ViewComposers\Tenant\CompanyViewComposer::class
        );

       /*view()->composer(
            'ecommerce',
            'Modules\Ecommerce\Http\ViewComposers\TakeProductoViewComposer'
        ); */
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
