<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Security\Permissions;

use Mautic\CoreBundle\Security\Permissions\AbstractPermissions;
use Symfony\Component\Form\FormBuilderInterface;

class WhatsAppPermissions extends AbstractPermissions
{
    public function __construct($params)
    {
        parent::__construct($params);
        $this->addStandardPermissions('categories');
        $this->addExtendedPermissions('messages');
    }

    public function getName(): string
    {
        return 'whatsapp';
    }

    public function buildForm(FormBuilderInterface &$builder, array $options, array $data): void
    {
        $this->addStandardFormFields('whatsapp', 'categories', $builder, $data);
        $this->addExtendedFormFields('whatsapp', 'messages', $builder, $data);
    }
}
