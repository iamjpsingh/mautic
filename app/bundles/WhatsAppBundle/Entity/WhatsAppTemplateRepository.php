<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Entity;

use Doctrine\ORM\EntityRepository;

/**
 * @author iamjpsingh
 *
 * @extends EntityRepository<WhatsAppTemplate>
 */
class WhatsAppTemplateRepository extends EntityRepository
{
    /**
     * @return WhatsAppTemplate[]
     */
    public function findApproved(): array
    {
        return $this->findBy(['status' => 'APPROVED'], ['name' => 'ASC']);
    }

    /**
     * @return WhatsAppTemplate[]
     */
    public function findByName(string $name): array
    {
        return $this->findBy(['name' => $name]);
    }

    public function findOneByMetaTemplateId(string $metaTemplateId): ?WhatsAppTemplate
    {
        return $this->findOneBy(['metaTemplateId' => $metaTemplateId]);
    }
}
