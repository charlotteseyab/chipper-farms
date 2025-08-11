<?php

namespace App\Helpers;

class FlashHelper
{
    /**
     * Flash a success message
     */
    public static function success($message)
    {
        session()->flash('success', $message);
    }

    /**
     * Flash an error message
     */
    public static function error($message)
    {
        session()->flash('error', $message);
    }

    /**
     * Flash a warning message
     */
    public static function warning($message)
    {
        session()->flash('warning', $message);
    }

    /**
     * Flash an info message
     */
    public static function info($message)
    {
        session()->flash('info', $message);
    }

    /**
     * Flash multiple messages at once
     */
    public static function messages(array $messages)
    {
        foreach ($messages as $type => $message) {
            if (in_array($type, ['success', 'error', 'warning', 'info'])) {
                session()->flash($type, $message);
            }
        }
    }

    /**
     * Flash a success message and redirect
     */
    public static function successAndRedirect($message, $route, $parameters = [])
    {
        self::success($message);
        return redirect()->route($route, $parameters);
    }

    /**
     * Flash an error message and redirect back
     */
    public static function errorAndBack($message)
    {
        self::error($message);
        return back();
    }

    /**
     * Flash validation errors
     */
    public static function validationErrors($errors)
    {
        if (is_array($errors)) {
            foreach ($errors as $field => $messages) {
                if (is_array($messages)) {
                    foreach ($messages as $message) {
                        session()->flash('error', $message);
                        break; // Only show first error per field
                    }
                } else {
                    session()->flash('error', $messages);
                }
                break; // Only show first field error
            }
        } else {
            session()->flash('error', $errors);
        }
    }

    /**
     * Check if there are any flash messages
     */
    public static function hasMessages()
    {
        return session()->has(['success', 'error', 'warning', 'info']);
    }

    /**
     * Get all flash messages
     */
    public static function getAllMessages()
    {
        return [
            'success' => session('success'),
            'error' => session('error'),
            'warning' => session('warning'),
            'info' => session('info'),
        ];
    }

    /**
     * Clear all flash messages
     */
    public static function clear()
    {
        session()->forget(['success', 'error', 'warning', 'info']);
    }
}
