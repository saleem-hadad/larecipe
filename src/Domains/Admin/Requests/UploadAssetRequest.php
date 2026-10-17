<?php

namespace LaRecipe\Domains\Admin\Requests;

use Illuminate\Http\UploadedFile;

/**
 * One file uploaded to the set's asset disk. It lands in _assets/ (or a
 * folder inside it), named after the upload, lowercased and stripped of
 * anything that is not safe in a URL.
 */
final class UploadAssetRequest extends AdminRequest
{
    public const EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'svg', 'ico', 'pdf', 'mp4', 'webm', 'css', 'txt', 'json', 'zip'];

    public function rules(): array
    {
        return [
            ...$this->scopeRules(),
            'file' => ['required', 'file', 'max:20480', 'extensions:'.implode(',', self::EXTENSIONS)],
            'directory' => ['nullable', 'string', 'max:120', 'regex:'.self::PATH_PATTERN],
        ];
    }

    public function upload(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');

        return $file;
    }

    /** "_assets/{directory}/{name}.{ext}", relative to the version root. */
    public function assetPath(): string
    {
        $upload = $this->upload();
        $extension = strtolower($upload->getClientOriginalExtension());
        $name = strtolower(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME));
        $name = trim((string) preg_replace('/[^a-z0-9]+/', '-', $name), '-');
        $directory = trim((string) $this->input('directory', ''), '/');

        return '_assets/'.($directory !== '' ? $directory.'/' : '').($name !== '' ? $name : 'file').($extension !== '' ? '.'.$extension : '');
    }
}
