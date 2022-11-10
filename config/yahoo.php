<?php
// Yahoo Inc.

return [
  'clientId'             => env('YAHOO_CLIENT_ID', ''),
  'appSecret'         => env('YAHOO_CLIENT_SECRET', ''),
  'redirectUri'       => env('YAHOO_REDIRECT_URI', ''),
  'scopes'            => env('YAHOO_SCOPES', ''),
  'authority'         => env('YAHOO_AUTHORITY', 'https://api.login.yahoo.com'),
  'authorizeEndpoint' => env('YAHOO_AUTHORIZE_ENDPOINT', '/oauth2/request_auth'),
  'tokenEndpoint'     => env('YAHOO_TOKEN_ENDPOINT', '/oauth2/get_token'),
  'grant_type'        => env('YAHOOGRANT_TYPE'),
  'response_type'     => 'code'
];