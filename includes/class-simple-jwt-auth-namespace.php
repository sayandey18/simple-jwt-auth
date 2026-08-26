<?php

/**
 * Define a `wrapper namespace` to load the library classes
 * and prevent conflicts with other plugins using the same library
 * with different versions.
 *
 * @link       https://github.com/sayandey18
 * @since      1.0.0
 *
 * @package    Simple_Jwt_Auth
 * @subpackage Simple_Jwt_Auth/includes
 * @author     Sayan Dey <mr.sayandey18@outlook.com>
 */

namespace Simple_Jwt_Auth\Firebase\JWT;

class JWT extends \Firebase\JWT\JWT {

}

class Key extends \Firebase\JWT\Key {

}

class ExpiredException extends \Firebase\JWT\ExpiredException {

}

class SignatureInvalidException extends \Firebase\JWT\SignatureInvalidException {

}

class BeforeValidException extends \Firebase\JWT\BeforeValidException {

}
