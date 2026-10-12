<?php

return [
    'accepted' => 'Debes aceptar :attribute.', 'required' => 'El campo :attribute es obligatorio.', 'present' => 'Falta el campo :attribute.',
    'email' => ':attribute debe ser un correo válido.', 'unique' => ':attribute ya está registrado.', 'exists' => ':attribute no existe.',
    'string' => ':attribute debe ser texto.', 'integer' => ':attribute debe ser entero.', 'numeric' => ':attribute debe ser un número.',
    'boolean' => ':attribute debe ser verdadero o falso.', 'array' => ':attribute debe ser una lista válida.', 'uuid' => ':attribute debe ser un identificador válido.',
    'confirmed' => 'La confirmación de :attribute no coincide.', 'regex' => 'El formato de :attribute no es válido.', 'in' => 'El valor de :attribute no está permitido.',
    'distinct' => ':attribute contiene valores repetidos.', 'date' => ':attribute debe ser una fecha válida.', 'after' => ':attribute debe ser posterior a :date.',
    'file' => ':attribute debe ser un archivo válido.', 'mimes' => ':attribute debe ser un archivo de tipo :values.', 'mimetypes' => ':attribute debe ser de tipo :values.',
    'min' => ['string' => ':attribute debe tener al menos :min caracteres.', 'numeric' => ':attribute debe ser al menos :min.', 'array' => ':attribute debe contener al menos :min elementos.', 'file' => ':attribute debe pesar al menos :min KB.'],
    'max' => ['string' => ':attribute no debe superar :max caracteres.', 'numeric' => ':attribute no debe superar :max.', 'array' => ':attribute no debe superar :max elementos.', 'file' => ':attribute no debe superar :max KB.'],
    'size' => ['string' => ':attribute debe contener :size caracteres.', 'numeric' => ':attribute debe ser :size.', 'array' => ':attribute debe contener :size elementos.', 'file' => ':attribute debe pesar :size KB.'],
    'password' => ['letters' => 'La contraseña debe contener letras.', 'mixed' => 'La contraseña debe incluir mayúsculas y minúsculas.', 'numbers' => 'La contraseña debe incluir números.', 'symbols' => 'La contraseña debe incluir símbolos.', 'uncompromised' => 'La contraseña no es segura.'],
    'attributes' => ['name' => 'nombre', 'title' => 'título', 'email' => 'correo', 'phone' => 'teléfono', 'password' => 'contraseña', 'current_password' => 'contraseña actual', 'slug' => 'ruta', 'sku' => 'SKU', 'message' => 'mensaje', 'quantity' => 'cantidad', 'customer.name' => 'nombre', 'customer.phone' => 'teléfono', 'customer.email' => 'correo', 'privacy_accepted' => 'la política de privacidad', 'consent_version' => 'versión de privacidad', 'owner_approved' => 'la aprobación del dueño', 'confirm' => 'la confirmación'],
];
