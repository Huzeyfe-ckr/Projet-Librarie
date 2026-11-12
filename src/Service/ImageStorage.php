<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Enregistre une image envoyée par un utilisateur et renvoie son URL publique.
 *
 * Le fichier est renommé avec un nom aléatoire : on ne fait jamais confiance au nom
 * ni à l'extension envoyés par le navigateur.
 */
final class ImageStorage
{
    public function __construct(
        #[Autowire('%app.uploads_dir%')]
        private readonly string $directory,
        #[Autowire('%app.uploads_url%')]
        private readonly string $publicUrl,
    ) {
    }

    /**
     * @param string $folder sous-dossier, par exemple « books » ou « avatars »
     */
    public function store(UploadedFile $file, string $folder): string
    {
        // guessExtension() se base sur le contenu réel du fichier (type MIME), pas sur son nom
        $filename = bin2hex(random_bytes(12)).'.'.($file->guessExtension() ?? 'jpg');
        $file->move($this->directory.'/'.$folder, $filename);

        return $this->publicUrl.'/'.$folder.'/'.$filename;
    }
}
