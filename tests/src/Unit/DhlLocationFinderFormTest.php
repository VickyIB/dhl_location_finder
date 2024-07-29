<?php

namespace Drupal\Tests\dhl_location_finder\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\dhl_location_finder\Form\DhlLocationFinderForm;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Drupal\dhl_location_finder\Controller\DhlLocationFinderController;
use Drupal\dhl_location_finder\Service\DhlApiService;
use Prophecy\Prophecy\ObjectProphecy;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * @coversDefaultClass \Drupal\dhl_location_finder\Form\DhlLocationFinderForm
 * @group dhl_location_finder
 */
class DhlLocationFinderFormTest extends UnitTestCase
{
    protected $form;
    protected $client;
    protected $stringTranslation;
    protected $messenger;
    protected $state;
    protected $controller;
    protected $dhlApiService;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(ClientInterface::class);
        $this->stringTranslation = $this->getMockBuilder(TranslationInterface::class)
        ->getMock();
        $this->messenger = $this->getMockBuilder(MessengerInterface::class)
        ->getMock();
        $this->state = $this->getMockBuilder(StateInterface::class)
        ->getMock();
        $this->configfactory = $this->getMockBuilder(ConfigFactoryInterface::class)
        ->getMock();

        // Update the DhlApiService mock creation to include constructor arguments.
        $this->dhl_api_service = $this->getMockBuilder(DhlApiService::class)
        ->setConstructorArgs([$this->client, $this->configfactory])
        ->getMock();

        $container = new ContainerBuilder();
        $container->set('http_client', $this->client);
        $container->set('string_translation', $this->stringTranslation);
        $container->set('messenger', $this->messenger);
        $container->set('state', $this->state);
        \Drupal::setContainer($container);

        $this->form = new DhlLocationFinderForm($this->client, $this->state, $this->messenger,  $this->configfactory, $this->dhl_api_service);
    }

    /**
     * Tests form structure.
     */
    public function testFormStructure()
    {
        $form = [];
        $form_state = $this->createMock(FormStateInterface::class);
        $form = $this->form->buildForm($form, $form_state);

        $this->assertArrayHasKey('country', $form);
        $this->assertArrayHasKey('city', $form);
        $this->assertArrayHasKey('postal_code', $form);
        $this->assertArrayHasKey('submit', $form);
    }

    /**
     * Tests form submission and API call.
     */
    public function testFormSubmission()
    {
        $form_state = new FormState();
        $form_state->setValue('country', 'DE');
        $form_state->setValue('city', 'Dresden');
        $form_state->setValue('postal_code', '01067');  

        $response = new Response(200, [], json_encode([
            'locations' => [
                [
                    'name' => 'Packstation 239',
                    'place' => [
                        'address' => [
                            'countryCode' => 'DE',
                            'postalCode' => '01159',
                            'addressLocality' => 'Dresden',
                            'streetAddress' => 'Löbtauer Str. 26'
                        ],
                    ],
                    'openingHours' => [
                        [
                            'opens' => '00:00:00',
                            'closes' => '23:59:00',
                            'dayOfWeek' => 'http://schema.org/Monday'
                        ],
                        [
                            'opens' => '00:00:00',
                            'closes' => '23:59:00',
                            'dayOfWeek' => 'http://schema.org/Tuesday'
                        ],
                        [
                            'opens' => '00:00:00',
                            'closes' => '23:59:00',
                            'dayOfWeek' => 'http://schema.org/Wednesday'
                        ],
                        [
                            'opens' => '00:00:00',
                            'closes' => '23:59:00',
                            'dayOfWeek' => 'http://schema.org/Thursday'
                        ],
                        [
                            'opens' => '00:00:00',
                            'closes' => '23:59:00',
                            'dayOfWeek' => 'http://schema.org/Friday'
                        ],
                        [
                            'opens' => '00:00:00',
                            'closes' => '23:59:00',
                            'dayOfWeek' => 'http://schema.org/Saturday'
                        ],
                        [
                            'opens' => '00:00:00',
                            'closes' => '23:59:00',
                            'dayOfWeek' => 'http://schema.org/Sunday'
                        ]
                    ]
                ]
            ]
        ]));

        $this->client->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->state->expects($this->once())
            ->method('set')
            ->with('dhl_location_finder.results', $this->anything());

        $form = [];
        $this->form->submitForm($form, $form_state);

        // Get the results from Drupal state.
        $results = \Drupal::state()->get('dhl_location_finder.results', '');

        $expected_output = <<<YAML
        -
            locationName: 'Packstation 239'
            address: { countryCode: DE, postalCode: '01159', addressLocality: Dresden, streetAddress: 'Löbtauer Str. 26' }
            openingHours: { Monday: '00:00:00 - 23:59:00', Tuesday: '00:00:00 - 23:59:00', Wednesday: '00:00:00 - 23:59:00', Thursday: '00:00:00 - 23:59:00', Friday: '00:00:00 - 23:59:00', Saturday: '00:00:00 - 23:59:00', Sunday: '00:00:00 - 23:59:00' }
        YAML;

        $this->assertSame($expected_output, $results);
    }
}
