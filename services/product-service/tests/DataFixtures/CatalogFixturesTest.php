<?php

declare(strict_types=1);

namespace App\Tests\DataFixtures;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\Product;
use App\Entity\Unit;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CatalogFixturesTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        $em = $this->em();
        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get('doctrine')->getManager();
    }

    public function testFixturesLoadCatalogWithMultipleVariants(): void
    {
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('doctrine:fixtures:load'));
        $tester->setInputs(['yes']);
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        $em = $this->em();
        self::assertSame(3, $em->getRepository(Brand::class)->count([]));
        self::assertSame(3, $em->getRepository(Unit::class)->count([]));
        self::assertSame(3, $em->getRepository(Category::class)->count([]));
        self::assertSame(8, $em->getRepository(Product::class)->count([]));

        $multiVariant = $em->createQueryBuilder()
            ->select('p.id')
            ->from(Product::class, 'p')
            ->join('p.productVariant', 'v')
            ->groupBy('p.id')
            ->having('COUNT(v.id) > 1')
            ->getQuery()
            ->getResult();

        self::assertNotEmpty($multiVariant);
    }
}
