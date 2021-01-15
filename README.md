# Very Specific Home Assistant Tools Written in PHP for my own Home Assistant install

This is probably literally no use to anyone else, but it's here anyway.

I switched everything to using Docker since I now run it on Docker, yay.

## Setup

Copy `.env.dist` to `.env`, and configure it. Descriptions of the environment variables are below.

## Worker

 - The worker sits there, grabbing sensor statuses from a Yale smart alarm system
 - Sensor statuses are then sent to Home Assistant webhook

### Environment variables required

 - `YALE_*` are the Yale smart alarm credentials
 - `HA_URL` is the Home Assistant URL
 - `HA_TOKEN` you must generate from your HA install (go to your own user profile, click `Create Token` in `Long-Lived Access Tokens` section at the bottom)
 - `INTERVAL` how often to poll Yale smart alarm and update HA (in seconds)

### Running it

To run it, `make run-worker`.

## Web

 - Displays a list of internet up/down times for a specific named entity `binary_sensor.internet_connectivity_8_8_8_8`

### Environment variables required

 - `DSN` is the Postgres DSN, which varies depending on security and DB configuration

### Running it

To run it, `make run-web`.

## Deployment

 - Run `make push DOCKER_REGISTRY=url-for-your-docker-registry` to push the `latest` tags
 - Run the Docker images on the Docker daemon as appropriate, with the environment variables required for each image
