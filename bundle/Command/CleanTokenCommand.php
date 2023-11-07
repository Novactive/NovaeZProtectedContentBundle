<?php
/**
 * NovaeZProtectedContentBundle.
 *
 * @package   Novactive\Bundle\eZProtectedContentBundle
 *
 * @author    Novactive
 * @copyright 2023 Novactive
 * @license   https://github.com/Novactive/eZProtectedContentBundle/blob/master/LICENSE MIT Licence
 */
declare(strict_types=1);

namespace Novactive\Bundle\eZProtectedContentBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Novactive\Bundle\eZProtectedContentBundle\Entity\ProtectedTokenStorage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CleanTokenCommand extends Command
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;

    }

    protected function configure(): void
    {
        $this
            ->setName('novaezprotectedcontent:cleantoken')
            ->setDescription('Remove expired token in the DB');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $dbQuery = $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(ProtectedTokenStorage::class, 'c')
            ->where('c.created < :nowMinusOneHour')
            ->setParameter('nowMinusOneHour', new \DateTime('now - 1 hours'));

        $entities = $dbQuery->getQuery()->getResult();

        foreach ($entities as $entity) {
            $this->entityManager->remove($entity);
        }

        $this->entityManager->flush();

        $io->success(sprintf('%d entities deleted', count($entities)));
        $io->success('Done.');
    }
}
