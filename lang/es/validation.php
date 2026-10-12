<?php
return [
    'required' => 'El campo :attribute es obligatorio.', 'email' => 'El campo :attribute debe ser un correo válido.',
    'unique' => 'El valor de :attribute ya está registrado.', 'exists' => 'La selección de :attribute no es válida.',
    'confirmed' => 'La confirmación de :attribute no coincide.', 'string' => 'El campo :attribute debe ser texto.',
    'integer' => 'El campo :attribute debe ser un número entero.', 'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'in' => 'La selección de :attribute no es válida.', 'enum' => 'La selección de :attribute no es válida.',
    'array' => 'El campo :attribute debe ser una lista.', 'file' => 'El campo :attribute debe ser un archivo.',
    'mimes' => 'El archivo debe ser JPG, PNG, PDF o TXT.', 'date_format' => 'El campo :attribute debe tener formato :format.',
    'min' => ['string' => ':attribute debe tener al menos :min caracteres.', 'array' => 'Selecciona al menos :min archivo.', 'numeric' => ':attribute debe ser al menos :min.'],
    'max' => ['string' => ':attribute no puede superar :max caracteres.', 'array' => 'Puedes adjuntar hasta :max archivos por operación.', 'file' => 'Cada archivo puede ocupar hasta 10 MB.', 'numeric' => ':attribute no puede superar :max.'],
    'password' => ['letters' => 'La contraseña debe incluir letras.', 'numbers' => 'La contraseña debe incluir números.'],
    'attributes' => ['subject' => 'asunto', 'description' => 'descripción', 'category_id' => 'categoría', 'email' => 'correo electrónico', 'password' => 'contraseña', 'name' => 'nombre', 'message' => 'mensaje', 'resolution' => 'solución', 'files' => 'archivos'],
];
