<?php

namespace App\Filament\Pages;

use App\Filament\Resources\EmailLogs\EmailLogResource;
use App\Filament\Resources\Flags\FlagResource;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Settings landing page. The brief groups Flags, Page pictures,
 * Email & Turnstile and Email log under one "Settings" entry — each
 * sub-section lives on its own page (because they are different
 * shapes: Flags is a list, Email settings is a form, Page pictures
 * is a media uploader, Email log is a list). This page is the single
 * nav entry that routes staff to the right sub-page.
 */
class Settings extends Page
{
    protected string $view = 'filament.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 20;

    protected static ?string $title = 'Settings';

    protected static ?string $navigationLabel = 'Settings';

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->is_staff;
    }

    /**
     * @return list<array{title: string, description: string, href: string, icon: BackedEnum}>
     */
    public function getSections(): array
    {
        return [
            [
                'title' => 'Flags',
                'description' => 'Beginner-friendly flags surfaced on events.',
                'href' => FlagResource::getUrl('index'),
                'icon' => Heroicon::OutlinedTag,
            ],
            [
                'title' => 'Email & Turnstile',
                'description' => 'Mailgun credentials, from address and Turnstile keys.',
                'href' => ManageMailSettings::getUrl(),
                'icon' => Heroicon::OutlinedEnvelope,
            ],
            [
                'title' => 'Page pictures',
                'description' => 'Hero and section imagery used across the public site.',
                'href' => ManagePagePictures::getUrl(),
                'icon' => Heroicon::OutlinedPhoto,
            ],
            [
                'title' => 'Email log',
                'description' => 'Audit of every transactional and marketing email sent.',
                'href' => EmailLogResource::getUrl('index'),
                'icon' => Heroicon::OutlinedQueueList,
            ],
        ];
    }
}
