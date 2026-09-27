<?php

namespace App\Livewire;

use App\Enums\EquipmentCategory;
use App\Models\ShooterEquipment;
use App\Models\User;
use Livewire\Component;

class ManageEquipment extends Component
{
    public string $category = 'rifle';

    public string $label = '';

    public string $notes = '';

    public function add(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        $this->validate([
            'category' => ['required', 'in:'.implode(',', array_column(EquipmentCategory::cases(), 'value'))],
            'label' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:200'],
        ]);

        $user->equipment()->create([
            'category' => $this->category,
            'label' => trim($this->label),
            'notes' => trim($this->notes) !== '' ? trim($this->notes) : null,
        ]);

        $this->reset('label', 'notes');
    }

    public function remove(int $id): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        ShooterEquipment::query()->where('user_id', $user->id)->whereKey($id)->delete();
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.manage-equipment', [
            'items' => $user instanceof User ? $user->equipment()->orderBy('category')->get() : collect(),
            'categories' => EquipmentCategory::cases(),
        ]);
    }
}
