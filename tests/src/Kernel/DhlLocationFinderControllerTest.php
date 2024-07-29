<?php

namespace Drupal\Tests\dhl_location_finder\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\dhl_location_finder\Controller\DhlLocationFinderController;

/**
 * @group dhl_location_finder
 */
class DhlLocationFinderControllerTest extends EntityKernelTestBase
{
    /**
     * {@inheritdoc}
     */
    protected static $modules = ['dhl_location_finder', 'system'];

    /**
     * Tests results retrieval.
     */
    public function testResults()
    {
        $config_factory = $this->container->get('config.factory');
        $controller = new DhlLocationFinderController($config_factory);

        // Add tests for the controller here.
    }
}
