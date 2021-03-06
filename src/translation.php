<?php
/**
 *
 * Licensed to the Apache Software Foundation (ASF) under one
 * or more contributor license agreements.  See the NOTICE file
 * distributed with this work for additional information
 * regarding copyright ownership.  The ASF licenses this file
 * to you under the Apache License, Version 2.0 (the
 * "License"); you may not use this file except in compliance
 * with the License.  You may obtain a copy of the License at
 * 
 *   http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 *
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @version //autogentag//
 * @filesource
 * @package Translation
 */

/**
 * ezcTranslation is a container that holds the translated strings for a
 * specific context.
 *
 * ezcTranslation objects are returned by the ezcTranslationManager for every
 * requested context.
 *
 * For an example see {@link ezcTranslationManager}.
 *
 * @package Translation
 * @version //autogentag//
 * @mainclass
 */
class ezcTranslation
{
    /**
     * Contains an array where the key is the original string (often
     * English) and the element is an object of the class
     * ezcTranslationData (a struct).
     *
     * @var array(string=>ezcTranslationData)
     */
    private $translationMap;

    /**
     * Constructs the ezcTranslation object.
     *
     * The constructor receives an array containing the translation elements,
     * and builds up an internal map between the original string and the
     * accompanying translation data.
     *
     * @param array(ezcTranslationData) $data
     */
    function __construct( array $data )
    {
        $this->translationMap = array();
        foreach ( $data as $translationElement )
        {
            $this->translationMap[$translationElement->original] = $translationElement;
        }
    }

    /**
     * Returns the replacement for the key $key from the parameters $params.
     *
     * The params array is an associative array in the form array('key'=>'value').
     *
     * This is a callback function used by the getTranslation() method for each
     * matched parameter in the translated string.
     *
     * @param string $key
     * @param array  $params
     * @return string
     */
    private function parameterCallback( $key, array $params )
    {
        if ( !isset( $params[strtolower( $key )] ) )
        {
            throw new ezcTranslationParameterMissingException( $key );
        }
        $string = $params[strtolower( $key )];
        // We use ctype_upper() here to check if the first character of the key
        // is an uppercase letter. If it is then we make the first character of
        // the returned translation also an upper case character. With this
        // mechanism we can correctly upper case translated strings if word
        // order changes. See
        // {@link ezcTranslationTest::testGetStringWithParameters} for an
        // example of this.
        if ( ctype_upper( $key[0] ) )
        {
            $string = ucfirst( $string );
        }
        return $string;
    }

    /**
     * Returns the translated version of the original string $key.
     *
     * This method returns a translated string and substitutes the parameters $param
     * in the localized string.
     *
     * @throws ezcTranslationKeyNotAvailableException when the key is not
     *         available in the translation definitions
     * @throws ezcTranslationParameterMissingException when not enough
     *         parameters are passed for a parameterized string
     * @param string $key
     * @param array(string=>string)  $params
     * @return string
     */
    public function getTranslation( $key, array $params = array() )
    {
        if ( !isset( $this->translationMap[$key] ) )
        {
            throw new ezcTranslationKeyNotAvailableException( $key );
        }
        $translatedString = $this->translationMap[$key]->translation;
        // Little optimization to prevent preg if not needed, it bails out too
        // if there is just a percent sign in the string without a valid
        // parameter-identifier, but we can live with that.
        if ( strstr( $translatedString, '%' ) === false )
        {
            return $translatedString;
        }
        // So we do have a possibility of a parameterized string, replace those
        // with the parameters. The callback function can actually throw an
        // exception to tell that there was a missing parameter.
        if ( version_compare( PHP_VERSION, '5.4.0' ) >= 0 )
        {
            return (string) preg_replace_callback( '@%(([A-Za-z][a-z_]*[a-z])|[1-9])@', function( $matches ) use ( $params ) {
                return $this->parameterCallback( $matches[1], $params );
            }, $translatedString );
        }
        else
        {
            return (string) preg_replace( '@%(([A-Za-z][a-z_]*[a-z])|[1-9])@e', '$this->parameterCallback("\\1", $params)', $translatedString );
        }
    }

    /**
     * Returns the replacement for the key $key from the parameters $params.
     *
     * The params array is an associative array in the form array('key'=>'value').
     *
     * This is a callback function used by the compileTranslation() method for each
     * matched parameter in the translated string.
     *
     * @param string $key
     * @param array  $params
     * @return string
     */
    private function parameterCallbackCompile( $key, array $params )
    {
        if ( !isset( $params[strtolower( $key )] ) )
        {
            throw new ezcTranslationParameterMissingException( $key );
        }
        // We use ctype_upper() here to check if the first character of the key
        // is an uppercase letter. If it is then we make the first character of
        // the returned translation also an upper case character. With this
        // mechanism we can correctly upper case translated strings if word
        // order changes. See
        // {@link ezcTranslationTest::testGetStringWithParameters} for an
        // example of this.
        if ( ctype_upper( $key[0] ) )
        {
            $string = "' . ucfirst(". $params[strtolower( $key )] . ") . '";
        }
        else
        {
            $string = "' . ". $params[strtolower( $key )] . " . '";
        }
        return $string;
    }

    /**
     * Returns the translated version of the original string $key.
     *
     * This method returns a translated string and substitutes the parameters $param
     * in the localized string with PHP code to place the variable data into
     * the string at a later moment. Instead of the values for each of the
     * parameters, an expression to get to the data should be sumbitted into
     * the $params array.
     *
     * <code>
     * echo $translation->compileTranslation( "Hello #%nr", array( "nr" => '$this->send->nr' ) );
     * </code>
     *
     * Will return something like:
     * <code>
     * 'Hallo #' . $this->send->nr . ''
     * </code>
     *
     * @param string $key
     * @param array(string=>string)  $params
     * @return string
     */
    public function compileTranslation( $key, array $params = array() )
    {
        if ( !isset( $this->translationMap[$key] ) )
        {
            throw new ezcTranslationKeyNotAvailableException( $key );
        }
        $translatedString = var_export( $this->translationMap[$key]->translation, true );
        // Little optimization to prevent preg if not needed, it bails out too
        // if there is just a percent sign in the string without a valid
        // parameter-identifier, but we can live with that.
        if ( strstr( $translatedString, '%' ) === false )
        {
            return $translatedString;
        }
        // So we do have a possibility of a parameterized string, replace those
        // with the parameters. The callback function can actually throw an
        // exception to tell that there was a missing parameter.
        if ( version_compare( PHP_VERSION, '5.4.0' ) >= 0 )
        {
            return (string) preg_replace_callback( '@%(([A-Za-z][a-z_]*[a-z])|[1-9])@', function( $matches ) use ( $params ) {
                return $this->parameterCallbackCompile( $matches[1], $params );
            }, $translatedString );
        }
        else
        {
            return (string) preg_replace( '@%(([A-Za-z][a-z_]*[a-z])|[1-9])@e', '$this->parameterCallbackCompile("\\1", $params)', $translatedString );
        }
    }
}
?>
