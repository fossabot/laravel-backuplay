<?php

namespace Gummibeer\Backuplay\Artisan;

use Gummibeer\Backuplay\Contracts\ConfigContract;
use Gummibeer\Backuplay\Exceptions\FileDoesNotExistException;
use Gummibeer\Backuplay\Helpers\Archive;
use Gummibeer\Backuplay\Helpers\File;
use Gummibeer\Backuplay\Parsers\Filename;
use Illuminate\Support\Facades\Storage;

/**
 * Class CreateBackup.
 */
class CreateBackup extends Command
{
    /**
     * @var string
     */
    protected $name = 'backup:create';
    /**
     * @var string
     */
    protected $description = 'Create and store a new backup';

    /**
     * @var \Gummibeer\Backuplay\Contracts\ConfigContract
     */
    protected $config;
    /**
     * @var array
     */
    protected $folders;
    /**
     * @var array
     */
    protected $files;

    /**
     * CreateBackup constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->config = app(ConfigContract::class);
    }

    /**
     * @return void
     * @throws \Gummibeer\Backuplay\Exceptions\EntityIsNoDirectoryException
     * @throws \Gummibeer\Backuplay\Exceptions\FileDoesNotExistException
     * @throws \Gummibeer\Backuplay\Exceptions\EntityIsNoFileException
     */
    public function fire()
    {
        $this->info('start backuplay');

        $this->folders = $this->config->getFolders();
        $this->comment('backup folders: '.implode(' ', $this->folders));
        $this->files = $this->config->getFiles();
        $this->comment('backup files: '.implode(' ', $this->files));

        if (count($this->folders) > 0 || count($this->files) > 0) {
            $tempDir = $this->config->getTempDir();
            $tempName = md5(uniqid(date('U'))).'.'.$this->config->get('extension');
            $tempPath = $tempDir.DIRECTORY_SEPARATOR.$tempName;
            $tempMeta = $this->createMetaFile($tempPath);
            $zippy = Archive::load();
            $archive = $zippy->create($tempPath, [
                'backup_info.txt' => $tempMeta,
            ]);
            $this->unlink($tempMeta);

            if (count($this->folders) > 0) {
                $this->comment('add folders to archive');
                foreach ($this->folders as $folder) {
                    $archive->addMembers($folder, true);
                }
            }

            if (count($this->files) > 0) {
                $this->comment('add files to archive');
                foreach ($this->files as $file) {
                    $archive->addMembers($file, false);
                }
            }

            File::isExisting($tempPath, true);
            File::isFile($tempPath, true);
            $this->info('created archive');
            $this->storeArchive($tempPath);
        } else {
            $this->warn('no valid folders or files to backup');
        }

        $this->info('end backuplay');
    }

    /**
     * @param string $tempPath
     * @return string
     */
    protected function createMetaFile($tempPath)
    {
        $tempPath = str_replace($this->config->get('extension'), 'txt', $tempPath);
        file_put_contents($tempPath, $this->getMetaContent());

        return $tempPath;
    }

    /**
     * @return string
     */
    protected function getMetaContent()
    {
        $content = [];
        $content[] = date('Y-m-d H:i:s T');
        $content[] = 'Folders:';
        if (count($this->folders) > 0) {
            foreach ($this->folders as $folder) {
                $content[] = '* '.$folder;
            }
        }
        $content[] = 'Files:';
        if (count($this->files) > 0) {
            foreach ($this->files as $file) {
                $content[] = '* '.$file;
            }
        }

        return implode(PHP_EOL, $content);
    }

    /**
     * @param string $tempPath
     * @return void
     * @throws \Gummibeer\Backuplay\Exceptions\FileDoesNotExistException
     */
    protected function storeArchive($tempPath)
    {
        $disk = $this->config->get('disk');
        if ($disk !== false) {
            $this->comment('store archive on disk: '.$disk);
            $filename = new Filename();
            foreach ($this->config->get('storage_cycle', []) as $cycle) {
                $filePath = implode(DIRECTORY_SEPARATOR, array_filter([
                    $this->config->get('storage_path'),
                    $filename->cycleParse($cycle),
                ]));
                Storage::disk($disk)->put($filePath, file_get_contents($tempPath));
                if (Storage::disk($disk)->exists($filePath)) {
                    $this->info('archive stored');
                } else {
                    throw new FileDoesNotExistException($filePath);
                }
            }
        } else {
            $this->warn('storage is disabled');
        }
        $this->unlink($tempPath);
    }
}
