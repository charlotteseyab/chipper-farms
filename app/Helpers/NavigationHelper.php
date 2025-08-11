<?php

namespace App\Helpers;

class NavigationHelper
{
    /**
     * Get the previous URL or a fallback URL
     */
    public static function getPreviousUrl($fallback = '/')
    {
        $previous = url()->previous();
        $current = url()->current();
        
        // If previous URL is the same as current (refresh case) or empty, use fallback
        if ($previous === $current || empty($previous) || $previous === url('/')) {
            return $fallback;
        }
        
        return $previous;
    }

    /**
     * Get back URL with intelligent fallback based on context
     */
    public static function getContextualBackUrl($context = null)
    {
        $previous = url()->previous();
        $current = url()->current();

        // Check if we have a stored last list URL from middleware
        $storedUrl = session('last_list_url');

        // If we have a valid previous URL that's different from current
        if ($previous !== $current && !empty($previous) && $previous !== url('/')) {
            // Prefer stored URL for certain contexts if available
            if ($storedUrl && in_array($context, ['pig-detail', 'sale-form']) && $storedUrl !== $current) {
                return $storedUrl;
            }
            return $previous;
        }

        // Use stored URL if available
        if ($storedUrl && $storedUrl !== $current) {
            return $storedUrl;
        }

        // Contextual fallbacks based on the current page
        return match($context) {
            'pig-detail' => self::getPigDetailFallback(),
            'sale-form' => self::getSaleFormFallback(),
            'mating' => route('mating.index'),
            'breeding-stocks' => route('breeding-stocks.index'),
            'standard-stocks' => route('standard-stocks.index'),
            'all-stocks' => route('all-stocks.index'),
            'sales' => route('sales.index'),
            'gestation' => route('gestation.index'),
            default => route('dashboard')
        };
    }

    /**
     * Get fallback URL for pig detail pages
     */
    private static function getPigDetailFallback()
    {
        // Try to determine from URL parameters or session
        $referer = request()->headers->get('referer');
        
        if ($referer) {
            if (str_contains($referer, 'breeding-stocks')) {
                return route('breeding-stocks.index');
            }
            if (str_contains($referer, 'standard-stocks')) {
                return route('standard-stocks.index');
            }
            if (str_contains($referer, 'all-stocks')) {
                return route('all-stocks.index');
            }
        }
        
        return route('all-stocks.index');
    }

    /**
     * Get fallback URL for sale form pages
     */
    private static function getSaleFormFallback()
    {
        return route('standard-stocks.index');
    }

    /**
     * Store the current URL as the intended back URL
     */
    public static function storeBackUrl($key = 'intended_back_url')
    {
        session([$key => url()->current()]);
    }

    /**
     * Get stored back URL
     */
    public static function getStoredBackUrl($key = 'intended_back_url', $fallback = null)
    {
        return session($key, $fallback);
    }

    /**
     * Clear stored back URL
     */
    public static function clearStoredBackUrl($key = 'intended_back_url')
    {
        session()->forget($key);
    }

    /**
     * Generate back button HTML with dynamic URL
     */
    public static function backButton($context = null, $classes = 'text-gray-500 hover:text-gray-700', $text = null)
    {
        $backUrl = self::getContextualBackUrl($context);
        $buttonText = $text ?? '';
        
        return sprintf(
            '<a href="%s" class="%s">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                %s
            </a>',
            $backUrl,
            $classes,
            $buttonText
        );
    }

    /**
     * Check if we should show a back button (not on home/dashboard)
     */
    public static function shouldShowBackButton()
    {
        $current = request()->route()->getName();
        $excludedRoutes = ['dashboard', 'home', 'login', 'register'];
        
        return !in_array($current, $excludedRoutes);
    }
}
