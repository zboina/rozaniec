<?php

namespace Rozaniec\RozaniecBundle\Twig;

use Rozaniec\RozaniecBundle\Service\RozaniecUserResolver;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

class RozaniecExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private string $baseTemplate,
        private RozaniecUserResolver $resolver,
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
        ];
    }

    public function getUserName(object $user): string
    {
        return $this->resolver->getFullName($user);
    }
}
