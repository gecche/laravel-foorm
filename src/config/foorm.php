<?php


/*
 * 'model' => <MODELNAME>
 * <FORMNAME> =>  [ //nome del form da route
 *      type => <FORMTYPE>, //tipo di form (opzionale se non c'è viene utilizzato il nome)
 *              //search, list, edit, insert, view, csv, pdf
 *      fields => [ //i campi del modello principale
 *          <FIELDNAME> => [
 *              'default' => <DEFAULTVALUE> //valore di default del campo (null se non presente)
 *              'options' => array|belongsto:<MODELNAME>|dboptions|boolean
 *                          //le opzioni possibili di un campo, prese da un array associativo,
 *                              da una relazione (gli id del modello correlato
 *                              dal database (enum ecc...)
 *                              booleano
 *              'nulloption' => true|false|onchoice //onchoice indica che l'opzione nullable è presente solo se i valori
 *                                  delle options sono più di uno; default: true,
 *              'null-label' => etichetta da associare al null
 *              'bool-false-value' => valore da associare al false
 *              'bool-false-label' => etichetta da associare al false
 *              'bool-true-value' => valore da associare al true
 *              'bool-true-label' => etichetta da associare al true
 *          ]
 *      ],
 *      relations => [ // le relazioni del modello principale
 *          <RELATIONNAME> => [
 *              fields => [ //i campi del modello principale
 *                  <FIELDNAME> => [
 *                      'default' => <DEFAULTVALUE> //valore di default del campo (null se non presente)
 *                      'options' => array|relation:<RELATIONNAME>|dboptions|boolean
 *                          //le opzioni possibili di un campo, prese da un array associativo,
 *                              da una relazione (gli id del modello correlato,
 *                              dal database (enum ecc...)
 *                              booleano
 *                      'nulloption' => true|false|onchoice //onchoice indica che l'opzione nullable è presente solo se i valori
 *                                    delle options sono più di uno; default: true,
 *                  ]
 *              ],
 *              savetype => [ //metodo di salvataggio della relazione
 *                              (in caso di edit/insert) da definire meglio
 *              ]
 *          ]
 *      ],
 *      params => [ // altri parametri opzionali
 *
 *      ],
 * ]
 */

return [

    'models_namespace' => "App\\Models\\",
    'foorms_namespace' => "App\\Foorm\\",
    'foorms_defaults_namespace' => "Gecche\\Foorm\\",

    'types_fallbacks' => [
        'insert' => 'edit',
    ],

    'bool-false-value' => 0,
    'bool-false-label' => 'No',
    'bool-true-value' => 1,
    'bool-true-label' => 'Sì',
    'null-value' => -1,
    'null-label' => 'Seleziona...',
    'any-value' => -1,
    'any-label' => 'Qualsiasi',
    'no-value' => -2,
    'no-label' => 'Nessun valore',

    'pagination' => [
        'per_page' => 10,
        'no_paginate_value' => 1000000,
        'pagination_steps' => [10, 25, 50, 100],
    ],

    'relations_save_types' => [
        'belongs_to_many' => 'standard', //standard, add, standard_with_save:<PivotModelName>
        'has_many' => 'standard', //standard, add
        'belongs_to' => 'standard', //standard, add, morphed
    ],

];
