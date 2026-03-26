<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Lang;

class AdminResetPasswordNotification extends ResetPassword
{
    /**
     * Get the reset URL for the notification
     * @param mixed $notifiable
     * @return string
     */
    protected function resetUrl($notifiable): string
    {
        return url(route('admin.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
    }

    /**
     * Build the mail message for the notification
     * @param string $url
     * @return MailMessage
     */
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject(Lang::get('Admin Password Reset'))
            ->line(Lang::get('You are receiving this email because we received a password reset request for your admin account.'))
            ->action(Lang::get('Reset Password'), $url)
            ->line(Lang::get('This password reset link will expire in :count minutes.', ['count' => config('auth.passwords.admins.expire')]))
            ->line(Lang::get('If you did not request a password reset, no further action is required.'));
    }
}
