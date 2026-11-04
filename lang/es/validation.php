<?php

// Mensajes de validación en español para las reglas que usa la API.
// Las reglas que no estén aquí usan el mensaje en inglés de Laravel.
return [
    'after' => 'El campo :attribute debe ser una fecha posterior a :date.',
    'after_or_equal' => 'El campo :attribute debe ser una fecha igual o posterior a :date.',
    'array' => 'El campo :attribute debe ser una lista.',
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'date' => 'El campo :attribute no es una fecha válida.',
    'date_format' => 'El campo :attribute no tiene el formato :format.',
    'distinct' => 'El campo :attribute tiene un valor repetido.',
    'email' => 'El campo :attribute debe ser un correo válido.',
    'exists' => 'El valor de :attribute no existe o no está disponible.',
    'in' => 'El valor de :attribute no es válido.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'max' => [
        'array' => 'El campo :attribute no debe tener más de :max elementos.',
        'numeric' => 'El campo :attribute no debe ser mayor que :max.',
        'string' => 'El campo :attribute no debe tener más de :max caracteres.',
    ],
    'min' => [
        'array' => 'El campo :attribute debe tener al menos :min elementos.',
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'numeric' => 'El campo :attribute debe ser un número.',
    'prohibited' => 'El campo :attribute no está permitido.',
    'required' => 'El campo :attribute es obligatorio.',
    'required_if' => 'El campo :attribute es obligatorio cuando :other es :value.',
    'required_without' => 'El campo :attribute es obligatorio cuando no se envía :values.',
    'string' => 'El campo :attribute debe ser texto.',
    'unique' => 'El valor de :attribute ya está registrado.',

    'attributes' => [
        'password' => 'contraseña',
        'servicio_id' => 'servicio',
        'cliente_id' => 'clienta',
    ],
];
