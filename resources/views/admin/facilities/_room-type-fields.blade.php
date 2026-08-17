{{-- Alpine modeline bağlı oda tipi alanları; $model, x-data içindeki nesnenin adıdır. --}}

<div>
    <label class="field-label">Oda tipi adı</label>
    <input type="text" name="name" x-model="{{ $model }}.name" required class="field-input">
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label class="field-label">Tür</label>
        <select name="kind" x-model="{{ $model }}.kind" class="field-input">
            <option value="room">Oda</option>
            <option value="villa">Villa</option>
        </select>
    </div>
    <div>
        <label class="field-label">Yatak sayısı</label>
        <input type="number" name="bed_count" min="1" max="20" x-model.number="{{ $model }}.bed_count" required class="field-input">
    </div>
    <div>
        <label class="field-label">Asgari ücretlendirilen kişi</label>
        <input type="number" name="min_billed_persons" min="1" max="20" x-model.number="{{ $model }}.min_billed_persons" class="field-input">
        <p class="field-hint">Villalar en az beş kişi üzerinden ücretlendirilir.</p>
    </div>
    <div>
        <label class="field-label">Azami kişi</label>
        <input type="number" name="max_persons" min="1" max="20" x-model.number="{{ $model }}.max_persons" class="field-input">
    </div>
    <div>
        <label class="field-label">Adet</label>
        <input type="number" name="quantity" min="0" max="500" x-model.number="{{ $model }}.quantity" required class="field-input">
    </div>
    <div>
        <label class="field-label">Boş yatak muafiyeti</label>
        <input type="number" name="waive_empty_bed_at_occupancy" min="1" max="20" x-model.number="{{ $model }}.waive_empty_bed_at_occupancy" class="field-input">
        <p class="field-hint">Bu kişi sayısında boş yatak ücreti alınmaz.</p>
    </div>
</div>

<div>
    <label class="field-label">Açıklama</label>
    <textarea name="description" rows="3" x-model="{{ $model }}.description" class="field-input"></textarea>
</div>

<label class="flex cursor-pointer items-center gap-2 text-sm">
    <input type="hidden" name="is_ground_floor" value="0">
    <input type="checkbox" name="is_ground_floor" value="1" x-model="{{ $model }}.is_ground_floor" class="rounded border-line text-accent-600 dark:text-accent-400 focus:ring-accent-500">
    Zemin kat (%10 indirim uygulanır)
</label>

<label class="flex cursor-pointer items-center gap-2 text-sm">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" name="is_active" value="1" x-model="{{ $model }}.is_active" class="rounded border-line text-accent-600 dark:text-accent-400 focus:ring-accent-500">
    Aktif
</label>
