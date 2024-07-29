<?php

namespace Drupal\dhl_location_finder\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\dhl_location_finder\Service\DhlApiService;

class DhlLocationFinderForm extends FormBase
{
    protected $httpClient;
    protected $state;
    protected $messenger;
    protected $configFactory;
    protected $dhlApiService;

    /**
     * {@inheritdoc}
     */
    public function __construct(ClientInterface $http_client, StateInterface $state, MessengerInterface $messenger, ConfigFactoryInterface $config_factory, DhlApiService $dhlApiService)
    {
        $this->httpClient = $http_client;
        $this->state = $state;
        $this->messenger = $messenger;
        $this->configFactory = $config_factory;
        $this->dhlApiService = $dhlApiService;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('http_client'),
            $container->get('state'),
            $container->get('messenger'),
            $container->get('config.factory'),
            $container->get('dhl_location_finder.dhl_api_service')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'dhl_location_finder_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $form['country'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Country'),
            '#required' => true,
        ];

        $form['city'] = [
            '#type' => 'textfield',
            '#title' => $this->t('City'),
            '#required' => true,
        ];

        $form['postal_code'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Postal Code'),
            '#required' => true,
        ];

        $form['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Find Locations'),
        ];

            return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        $country = $form_state->getValue('country');
        $city = $form_state->getValue('city');
        $postal_code = $form_state->getValue('postal_code');
    
        // Validate country code: must be exactly 2 capital letters.
        if (!preg_match('/^[A-Z]{2}$/', $country)) {
          $form_state->setErrorByName('country', $this->t('The country code must be exactly 2 capital letters (e.g., DE).'));
        }
    
        // Validate city: must not be empty.
        if (empty($city)) {
          $form_state->setErrorByName('city', $this->t('City field cannot be empty.'));
        }
    
        // Validate postal code: must be numeric.
        if (!ctype_digit($postal_code)) {
          $form_state->setErrorByName('postal_code', $this->t('The postal code must contain only numbers.'));
        }
      }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $country = $form_state->getValue('country');
        $city = $form_state->getValue('city');
        $postal_code = $form_state->getValue('postal_code');

        try {
            // Make API request to DHL.
            $response = $this->dhlApiService->getLocations($country, $city, $postal_code);
            if ($response) {
                $data = $response;
                if (isset($data['locations'])) {
                    $locations = [];
                    foreach ($data['locations'] as $location) {
                        if ($this->isLocationValid($location)) {
                            $filtered_location = [
                                'locationName' => $location['name'],
                                'address' => $location['place']['address'],
                                'openingHours' => []
                            ];
        
                            foreach ($location['openingHours'] as $opening_hours) {
                                $day_of_week_array = explode("/", $opening_hours['dayOfWeek']);
                                $day_of_week = end($day_of_week_array);
                                $filtered_location['openingHours'][$day_of_week] = $opening_hours['opens'] . " - "
                                    . $opening_hours['closes'];
                            }
        
                            $filtered_locations[] = $filtered_location;
                        }
                    }
                    $yaml_output = Yaml::dump($filtered_locations);
                    $this->state->set('dhl_location_finder.results', $yaml_output);
                    $this->messenger->addStatus('State set with data: ' . $yaml_output);  // Add this line for debugging
                    $form_state->setRedirect('dhl_location_finder.results');
                }
                else {
                    // Handle the case where 'locations' key is missing or null.
                    $this->messenger->addError($this->t('No locations found.'));
                }
            }
            else {
                // Handle the case where the response is not an instance of ResponseInterface.
                $this->messenger->addError($this->t('Invalid response from the API.'));
            }
        }
        catch (\Exception $e) {
            // Log exception and display an error message.
            \Drupal::logger('dhl_location_finder')->error($e->getMessage());
            $this->messenger->addError($this->t('An error occurred while retrieving locations.'));
        }
    }

    /**
     * Formats opening hours.
     *
     * @param array $opening_hours
     *   The opening hours array.
     *
     * @return array
     *   The formatted opening hours.
     */
    private function formatOpeningHours(array $opening_hours) {
        $formatted_hours = [];
        foreach ($opening_hours as $hours) {
            $day_of_week = str_replace('http://schema.org/', '', $hours['dayOfWeek']);
            $formatted_hours[$day_of_week] = $hours['opens'] . ' - ' . $hours['closes'];
        }
        return $formatted_hours;
    }

    /**
     * {@inheritdoc}
     */
    private function isLocationValid(array $location)
    {
        // Check if the location works on weekends.
        $works_on_weekends = array_reduce($location['openingHours'], function ($carry, $opening_hours) {
            $day_of_week_array = explode("/", $opening_hours['dayOfWeek']);
            $day_of_week = end($day_of_week_array);
            return $carry || in_array($day_of_week, ['Saturday', 'Sunday']);
        }, false);

        // Check if the location address has an odd number.
        $address_parts = explode(' ', $location['place']['address']['streetAddress']);
        $street_number = end($address_parts);
        $is_odd_number = (int)$street_number % 2 !== 0;

        return $works_on_weekends && !$is_odd_number;
    }
}
