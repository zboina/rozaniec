<?php

namespace Rozaniec\RozaniecBundle\Twig;

use Rozaniec\RozaniecBundle\Repository\RozaniecConfigRepository;
use Rozaniec\RozaniecBundle\Service\RozaniecUserResolver;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

class RozaniecExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private string $baseTemplate,
        private RozaniecUserResolver $resolver,
        private RozaniecConfigRepository $configRepo,
    ) {
    }

    public function getGlobals(): array
    {
        return [
            'rozaniec_base_template' => $this->baseTemplate,
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('rozaniec_name', [$this, 'getUserName']),
            new TwigFunction('rozaniec_config', [$this, 'getConfig']),
        ];
    }

    public function getUserName(object $user): string
    {
        return $this->resolver->getFullName($user);
    }

    public function getConfig(string $klucz): ?string
    {
        return $this->configRepo->get($klucz);
    }
}
