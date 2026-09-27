<div>
    <h2>Equipment</h2>
    <ul>
        @foreach ($items as $item)
            <li>
                {{ $item->category->getLabel() }} — {{ $item->label }}
                @if ($item->notes) <span>({{ $item->notes }})</span> @endif
                <button type="button" wire:click="remove({{ $item->id }})">Remove</button>
            </li>
        @endforeach
    </ul>
    <form wire:submit="add" class="enquiry-form">
        <label class="field">
            <span>Category</span>
            <select wire:model="category">
                @foreach ($categories as $category)
                    <option value="{{ $category->value }}">{{ $category->getLabel() }}</option>
                @endforeach
            </select>
        </label>
        <label class="field">
            <span>Name</span>
            <input type="text" wire:model="label" maxlength="120" placeholder="e.g. 6.5 Creedmoor">
            @error('label') <span class="err">{{ $message }}</span> @enderror
        </label>
        <label class="field">
            <span>Notes</span>
            <input type="text" wire:model="notes" maxlength="200">
        </label>
        <button type="submit" class="btn">Add equipment</button>
    </form>
</div>
