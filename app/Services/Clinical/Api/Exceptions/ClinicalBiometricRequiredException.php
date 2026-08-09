<?php

namespace App\Services\Clinical\Api\Exceptions;

/**
 * 428 BIOMETRIC_REAUTH_REQUIRED (API Integration Guide §8.2) — an off-premises
 * request needs a fresh biometric challenge before it will be honoured.
 *
 * The recovery is a three-step dance the *client device* has to perform, not
 * something this server can do on its behalf:
 *
 *   1. POST /clinical/device/challenge with the device UUID
 *   2. have the device sign the returned challenge (RSA-SHA256, base64)
 *   3. retry the original request with X-KashTre-Device-UUID,
 *      -Challenge-Payload and -Biometric-Signature
 *
 * Challenges are single-use and expire in five minutes, so this cannot be
 * pre-fetched and cached. Surface it to the UI as "confirm your identity",
 * never as a failure.
 *
 * Note the device gate ships *disabled* (§14) — until the first enrollment
 * cohort is live this exception will not be thrown in practice.
 */
class ClinicalBiometricRequiredException extends ClinicalApiException
{
}
