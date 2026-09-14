<?php

namespace App\Filament\Pages\Auth;

use App\Enums\DigestFrequency;
use App\Enums\Province;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getHomeProvinceFormComponent(),
                $this->getTravelRadiusFormComponent(),
                $this->getDigestFrequencyFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
            ]);
    }

    protected function getHomeProvinceFormComponent(): Component
    {
        return Select::make('home_province')
            ->label('Home province')
            ->options(Province::class)
            ->placeholder('Optional');
    }

    protected function getTravelRadiusFormComponent(): Component
    {
        return TextInput::make('travel_radius_km')
            ->label('Travel radius (km)')
            ->numeric()
            ->minValue(0)
            ->maxValue(2000)
            ->helperText('How far you are usually willing to drive for a match.');
    }

    protected function getDigestFrequencyFormComponent(): Component
    {
        return Select::make('digest_frequency')
            ->label('Email digest')
            ->options(DigestFrequency::class)
            ->default(DigestFrequency::None->value);
    }
}
