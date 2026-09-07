<?php

namespace App\View\Composers;

use App\Helpers\ThemeHelper;
use Illuminate\View\View;

class ThemeComposer
{
    /**
     * Bind data to the view.
     */
    public function compose(View $view): void
    {
        $themeSettings = ThemeHelper::getThemeSettings();
        $themeCss = ThemeHelper::getThemeCss();
        
        $view->with([
            'themeSettings' => $themeSettings,
            'themeCss' => $themeCss,
        ]);
    }
}