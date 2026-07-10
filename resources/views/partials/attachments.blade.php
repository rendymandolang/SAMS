<section class="card" style="margin-top:18px;">
    <div class="toolbar">
        <div>
            <h2 style="margin-bottom:6px;">Attachments</h2>
            <p class="muted" style="margin:0;">Upload dokumen pendukung seperti invoice, foto, surat jalan, quotation, atau bukti approval.</p>
        </div>
        <span class="badge">{{ number_format($attachments->count()) }} files</span>
    </div>

    <form method="POST" action="{{ route('attachments.store', [$attachmentType, $attachmentId]) }}" enctype="multipart/form-data" style="margin-bottom:16px;">
        @csrf
        <div style="display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap;">
            <input class="input" style="max-width:420px;" name="attachment" type="file" required>
            <button class="button inline" type="submit">Upload File</button>
        </div>
        <p class="muted" style="margin:8px 0 0;font-size:12px;">Maks 5MB. Format: PDF, image, Word, Excel, CSV, TXT.</p>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>File</th>
                <th>Type</th>
                <th>Size</th>
                <th>Uploaded By</th>
                <th>Uploaded At</th>
                <th style="text-align:right;">Action</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($attachments as $attachment)
                <tr>
                    <td><strong>{{ $attachment->original_name }}</strong></td>
                    <td>{{ $attachment->mime_type ?: '-' }}</td>
                    <td>{{ number_format(((float) $attachment->size) / 1024, 1, ',', '.') }} KB</td>
                    <td>{{ $attachment->uploader_name }}</td>
                    <td>{{ \Illuminate\Support\Carbon::parse($attachment->created_at)->format('d M Y H:i') }}</td>
                    <td>
                        <div class="actions">
                            <a class="link-action" href="{{ route('attachments.download', $attachment->id) }}">Download</a>
                            <form method="POST" action="{{ route('attachments.destroy', $attachment->id) }}" onsubmit="return confirm('Hapus attachment ini?')">
                                @csrf
                                @method('DELETE')
                                <button class="link-action danger" type="submit">Hapus</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6"><div class="empty-state">Belum ada attachment.</div></td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
