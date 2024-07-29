# DHL Location Finder Module

## Overview

The DHL Location Finder module for Drupal allows you to configure and use the DHL Location Finder API to find locations based on user input. 

## Installation

1. **Enable the Module**:
   - Navigate to `Extend` in the Drupal admin toolbar.
   - Find the **DHL Location Finder** module in the list and check the box.
   - Click **Install** to enable the module.
   
   OR

   - drush en dhl_location_finder

## Configuration

To configure the DHL API key and DHL API base URL for the module, follow these steps:

1. **Access DHL API Configuration**:
   - In the Configuration page, locate the **DHL Location Finder Settingsr** section.
   - Click on **DHL Location Finder Settings** or navigate directly to the configuration form using the URL:
     ```
     /admin/config/dhl-location-finder
     ```

2. **Set the API Key and API location finder base URL**:
   - On the DHL Location Finder Settings form, enter your DHL API key and DHL API base URL in the provided text field.
   - Click **Save Configuration** to store your API key.

## Using the Location Finder Form

To use the location finder form where users can enter their search criteria, follow these steps:

1. **Navigate to the Location Finder Form**:
   - The form can be accessed through the URL:
     ```
     /dhl-location-finder
     ```

2. **Submit a Query**:
   - On the location finder form, fill in the following fields:
     - **Country**: Enter a two-letter country code (e.g., `DE` for the Germany).
     - **City**: Enter the name of the city (e.g. Dresden).
     - **Postal Code**: Enter the postal code (e.g. 01067)

3. **View Results**:
   - After submitting the form, the results will be displayed on the dhl-location-finder/results page, showing the locations that match the query.

4. **Run test cases**
   - command: vendor/bin/phpunit