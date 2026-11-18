{{-- $experience = model saat mengubah; kosong saat menambah --}}
@php $exp = $experience ?? null; @endphp

<input type="text" name="title" required maxlength="100" value="{{ $exp?->title }}"
       placeholder="Jabatan / jenis pekerjaan *" class="w-full rounded-lg border p-2 text-sm">
<input type="text" name="workplace" maxlength="100" value="{{ $exp?->workplace }}"
       placeholder="Tempat bekerja" class="w-full rounded-lg border p-2 text-sm">
<div class="flex gap-2">
    <input type="date" name="started_at" value="{{ $exp?->started_at?->format('Y-m-d') }}"
           class="w-full rounded-lg border p-2 text-sm" title="Tanggal mulai">
    <input type="date" name="ended_at" value="{{ $exp?->ended_at?->format('Y-m-d') }}"
           class="w-full rounded-lg border p-2 text-sm" title="Tanggal selesai (kosongkan jika masih bekerja)">
</div>
<textarea name="description" rows="3" maxlength="500" placeholder="Deskripsi singkat"
          class="w-full rounded-lg border p-2 text-sm">{{ $exp?->description }}</textarea>

