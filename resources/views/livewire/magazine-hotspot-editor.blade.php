<div class="hotspot-editor">
    <p>Drag a rectangle on the page. A box you draw replaces an extracted link where they overlap. Clicks are counted per advertiser.</p>

    @if ($summary !== [])
        <ul>
            @foreach ($summary as $row)
                <li>{{ $row['name'] }}: {{ $row['clicks'] }} {{ $row['clicks'] === 1 ? 'click' : 'clicks' }}</li>
            @endforeach
        </ul>
    @else
        <p>No advertiser clicks yet.</p>
    @endif

    <label>
        <span>Page</span>
        <input type="number" min="1" wire:model.live="page">
    </label>

    <div
        class="hotspot-stage"
        wire:key="page-{{ $page }}"
        x-data="{
            draft: null,
            start(event) {
                if (event.target.closest('.hotspot-box')) return;
                const rect = this.$el.getBoundingClientRect();
                const x = ((event.clientX - rect.left) / rect.width) * 100;
                const y = ((event.clientY - rect.top) / rect.height) * 100;
                this.draft = { x, y, w: 0, h: 0, originX: x, originY: y };
                event.preventDefault();
            },
            move(event) {
                if (!this.draft) return;
                const rect = this.$el.getBoundingClientRect();
                const x = ((event.clientX - rect.left) / rect.width) * 100;
                const y = ((event.clientY - rect.top) / rect.height) * 100;
                this.draft.x = Math.max(0, Math.min(this.draft.originX, x));
                this.draft.y = Math.max(0, Math.min(this.draft.originY, y));
                this.draft.w = Math.min(100 - this.draft.x, Math.abs(x - this.draft.originX));
                this.draft.h = Math.min(100 - this.draft.y, Math.abs(y - this.draft.originY));
            },
            finish() {
                if (!this.draft || this.draft.w < 1 || this.draft.h < 1) {
                    this.draft = null;
                    return;
                }
                $wire.setBox(this.draft.x, this.draft.y, this.draft.w, this.draft.h);
                this.draft = null;
            }
        }"
        @pointerdown="start($event)"
        @pointermove="move($event)"
        @pointerup="finish()"
    >
        @if ($pdfUrl)
            <iframe title="Magazine page {{ $page }}" src="{{ $pdfUrl }}#page={{ $page }}"></iframe>
        @else
            <p>This issue has no PDF yet.</p>
        @endif
        @foreach ($pageHotspots as $hotspot)
            <button
                type="button"
                class="hotspot-box"
                style="left: {{ $hotspot->x }}%; top: {{ $hotspot->y }}%; width: {{ $hotspot->w }}%; height: {{ $hotspot->h }}%;"
                wire:click="edit({{ $hotspot->id }})"
                @pointerdown.stop
            >{{ $hotspot->displayName() }}</button>
        @endforeach
        <div class="hotspot-draft" x-show="draft" x-cloak :style="draft && `left:${draft.x}%;top:${draft.y}%;width:${draft.w}%;height:${draft.h}%`"></div>
    </div>

    <form wire:submit="save">
        <label>
            <span>Link</span>
            <input type="text" wire:model="url" maxlength="2000" required placeholder="https:// or email">
        </label>
        @error('url') <p>{{ $message }}</p> @enderror
        <label>
            <span>Label</span>
            <input type="text" wire:model="label" maxlength="160">
        </label>
        <label>
            <span>Advertiser</span>
            <input type="text" wire:model="advertiser" maxlength="160">
        </label>
        <label>
            <span>Directory listing</span>
            <input type="search" wire:model.live.debounce.300ms="orgSearch" placeholder="Search organisations">
        </label>
        <select wire:model="organisationId">
            <option value="">None</option>
            @foreach ($organisations as $organisation)
                <option value="{{ $organisation->id }}">{{ $organisation->name }}</option>
            @endforeach
        </select>
        <p>Box: {{ $x }}% , {{ $y }}% , {{ $w }}% × {{ $h }}%</p>
        <button type="submit" wire:loading.attr="disabled">
            <span wire:loading.remove>{{ $editingId ? 'Update link' : 'Add link' }}</span>
            <span wire:loading>Saving…</span>
        </button>
        @if ($editingId)
            <button type="button" wire:click="resetBox">Cancel</button>
        @endif
    </form>

    <table>
        <thead>
            <tr>
                <th>Page</th>
                <th>Advertiser</th>
                <th>Clicks</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($hotspots as $hotspot)
                <tr>
                    <td>{{ $hotspot->page }}</td>
                    <td>{{ $hotspot->displayName() }}</td>
                    <td>{{ $hotspot->clicks_count }}</td>
                    <td>
                        <button type="button" wire:click="edit({{ $hotspot->id }})">Edit</button>
                        @if ($hotspot->source === 'manual')
                            <button type="button" wire:click="delete({{ $hotspot->id }})">Delete</button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">No links on this issue yet. Drag a rectangle on the page to add one.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <style>
        .hotspot-editor { display: grid; gap: 14px; }
        .hotspot-stage { position: relative; width: min(520px, 100%); aspect-ratio: 3/4; background: #12181a; touch-action: none; }
        .hotspot-stage iframe { width: 100%; height: 100%; border: 0; pointer-events: none; }
        .hotspot-box, .hotspot-draft { position: absolute; border: 2px solid #d9ae52; background: rgba(217, 174, 82, .28); color: #1a1405; font-size: 12px; }
        .hotspot-editor label { display: flex; flex-direction: column; gap: 4px; }
        .hotspot-editor input, .hotspot-editor select { min-height: 44px; }
        .hotspot-editor button { min-height: 44px; }
    </style>
</div>
