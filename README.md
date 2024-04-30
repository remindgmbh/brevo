# REMIND - Brevo

This extensions adds a form finisher to create a new Brevo contact using double-opt-in.


## Installation

Use composer to install the extension using `composer install remind/brevo`. Import typoscript in backend or your provider extension.


## Configuration

- set *BREVO_API_KEY* environment variable
- add the *Brevo Registration Finisher* to a form 
- set comma separated list of Brevo Contact List IDs in finisher configuration
- set double-opt-in Brevo Template ID in finisher configuration
- set redirect URL (after DOI-Mail confirmation link has been clicked) in finisher configuration
