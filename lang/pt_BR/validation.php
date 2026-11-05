<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'Este registro deve ser aceito.',
    'accepted_if' => 'Este registro deve ser aceito quando :other for :value.',
    'active_url' => 'Este registro deve ser uma URL válida.',
    'after' => 'Este registro deve ser uma data posterior a :date.',
    'after_or_equal' => 'Este registro deve ser uma data posterior ou igual a :date.',
    'alpha' => 'Este registro só pode conter letras.',
    'alpha_dash' => 'Este registro só pode conter letras, números e traços.',
    'alpha_num' => 'Este registro só pode conter letras e números.',
    'array' => 'Este registro deve ser uma matriz.',
    'ascii' => 'Este registro deve conter somente caracteres alfanuméricos.',
    'before' => 'Este registro deve ser uma data anterior :date.',
    'before_or_equal' => 'Este registro deve ser uma data anterior ou igual a :date.',
    'between' => [
        'numeric' => 'Este registro deve ser entre :min e :max.',
        'file' => 'Este registro deve ser entre :min e :max kilobytes.',
        'string' => 'Este registro deve ser entre :min e :max caracteres.',
        'array' => 'Este registro deve ter entre :min e :max itens.',
    ],
    'boolean' => 'Este registro deve ser verdadeiro ou falso.',
    'can' => 'Este registro contém valores não autorizado.',
    'confirmed' => 'Este registro de confirmação não confere.',
    'contains' => 'Este registro está faltando um valor obrigatório.',
    'current_password' => 'A senha está incorreta.',
    'date' => 'Este registro deve ser uma data válida.',
    'date_equals' => 'Este registro deve ser uma data igual a :date.',
    'date_format' => 'Este registro não corresponde ao formato :format.',
    'decimal' => 'Este registro deve ter :decimal casas decimais.',
    'declined' => 'Este registro deve ser recusado.',
    'declined_if' => 'Este registro deve ser recusado quando :other for :value.',
    'different' => 'Este registro e :other devem ser diferentes.',
    'digits' => 'Este registro deve ter :digits dígitos.',
    'digits_between' => 'Este registro deve ter entre :min e :max dígitos.',
    'dimensions' => 'Este registro tem dimensões de imagem inválidas.',
    'distinct' => 'Este registro tem um valor duplicado.',
    'doesnt_end_with' => 'Este registro não pode terminar com um dos seguintes: :values.',
    'doesnt_start_with' => 'Este registro não pode começar com um dos seguintes: :values.',
    'email' => 'Este registro deve ser um endereço de e-mail válido.',
    'ends_with' => 'Este registro deve terminar com um dos seguintes: :values',
    'enum' => 'O campo selecionado é inválido.',
    'exists' => 'O campo selecionado é inválido.',
    'extensions' => 'Este registro deve conter uma das seguintes extensões: :values.',
    'file' => 'Este registro deve ser um arquivo.',
    'filled' => 'Este registro deve ter um valor.',
    'gt' => [
        'numeric' => 'Este registro deve ser maior que :value.',
        'file' => 'Este registro deve ser maior que :value kilobytes.',
        'string' => 'Este registro deve ser maior que :value caracteres.',
        'array' => 'Este registro deve conter mais de :value itens.',
    ],
    'gte' => [
        'numeric' => 'Este registro deve ser maior ou igual a :value.',
        'file' => 'Este registro deve ser maior ou igual a :value kilobytes.',
        'string' => 'Este registro deve ser maior ou igual a :value caracteres.',
        'array' => 'Este registro deve conter :value itens ou mais.',
    ],
    'hex_color' => 'Este registro deve ser uma cor hexadecimal válida.',
    'image' => 'Este registro deve ser uma imagem.',
    'in' => 'O campo selecionado é inválido.',
    'in_array' => 'Este registro não existe em :other.',
    'integer' => 'Este registro deve ser um número inteiro.',
    'ip' => 'Este registro deve ser um endereço de IP válido.',
    'ipv4' => 'Este registro deve ser um endereço IPv4 válido.',
    'ipv6' => 'Este registro deve ser um endereço IPv6 válido.',
    'json' => 'Este registro deve ser uma string JSON válida.',
    'list' => 'Este registro deve ser uma lista.',
    'lowercase' => 'Este registro deve estar em letras minúsculas.',
    'lt' => [
        'numeric' => 'Este registro deve ser menor que :value.',
        'file' => 'Este registro deve ser menor que :value kilobytes.',
        'string' => 'Este registro deve ser menor que :value caracteres.',
        'array' => 'Este registro deve conter menos de :value itens.',
    ],
    'lte' => [
        'numeric' => 'Este registro deve ser menor ou igual a :value.',
        'file' => 'Este registro deve ser menor ou igual a :value kilobytes.',
        'string' => 'Este registro deve ser menor ou igual a :value caracteres.',
        'array' => 'Este registro não deve conter mais que :value itens.',
    ],
    'mac_address' => 'Este registro deve ser um MAC address válido.',
    'max' => [
        'numeric' => 'Este registro não pode ser superior a :max.',
        'file' => 'Este registro não pode ser superior a :max kilobytes.',
        'string' => 'Este registro não pode ser superior a :max caracteres.',
        'array' => 'Este registro não pode ter mais do que :max itens.',
    ],
    'max_digits' => 'Este registro não pode ser superior a :max dígitos',
    'mimes' => 'Este registro deve ser um arquivo do tipo: :values.',
    'mimetypes' => 'Este registro deve ser um arquivo do tipo: :values.',
    'min' => [
        'numeric' => 'Este registro deve ser pelo menos :min.',
        'file' => 'Este registro deve ter pelo menos :min kilobytes.',
        'string' => 'Este registro deve ter pelo menos :min caracteres.',
        'array' => 'Este registro deve ter pelo menos :min itens.',
    ],
    'missing' => 'Este registro deve estar ausente.',
    'missing_if' => 'Este registro deve estar ausente quando :other for :value.',
    'missing_unless' => 'Este registro deve estar ausente, a menos que :other seja :value.',
    'missing_with' => 'Este registro não deve estar presente quando houver :values.',
    'missing_with_all' => 'Este registro deve estar ausente quando :values estiverem presentes.',
    'min_digits' => 'Este registro deve ter pelo menos :min dígitos',
    'not_in' => 'O campo selecionado é inválido.',
    'multiple_of' => 'Este registro deve ser um múltiplo de :value.',
    'not_regex' => 'Este registro possui um formato inválido.',
    'numeric' => 'Este registro deve ser um número.',
    'password' => [
        'letters' => 'Este registro deve conter pelo menos uma letra.',
        'mixed' => 'Este registro deve conter pelo menos uma letra maiúscula e uma letra minúscula.',
        'numbers' => 'Este registro deve conter pelo menos um número.',
        'symbols' => 'Este registro deve conter pelo menos um símbolo.',
        'uncompromised' => 'A senha que você inseriu está em um vazamento de dados.'
            .' Por favor escolha uma senha diferente.',
    ],
    'present' => 'Este registro deve estar presente.',
    'present_if' => 'Este registro deve estar presente quando :other for :value.',
    'present_unless' => 'Este registro deve estar presente, a menos que :other seja :value.',
    'present_with' => 'Este registro deve estar presente quando :values estiver presente.',
    'present_with_all' => 'Este registro deve estar presente quando :values estiverem presentes.',
    'regex' => 'Este registro tem um formato inválido.',
    'required' => 'Esse campo é obrigatório.',
    'required_array_keys' => 'Este registro deve conter entradas para: :values.',
    'required_if' => 'Este registro é obrigatório quando :other for :value.',
    'required_if_accepted' => 'Este registro é obrigatório quando :other for aceito.',
    'required_if_declined' => 'Este registro é obrigatório quando :other for recusado.',
    'required_unless' => 'Este registro é obrigatório exceto quando :other for :values.',
    'required_with' => 'Este registro é obrigatório quando :values está presente.',
    'required_with_all' => 'Este registro é obrigatório quando :values está presente.',
    'required_without' => 'Este registro é obrigatório quando :values não está presente.',
    'required_without_all' => 'Este registro é obrigatório quando nenhum dos :values estão presentes.',
    'prohibited' => 'Este registro é proibido.',
    'prohibited_if' => 'Este registro é proibido quando :other for :value.',
    'prohibited_unless' => 'Este registro é proibido exceto quando :other for :values.',
    'prohibits' => 'Este registro proíbe :other de estar presente.',
    'same' => 'Este registro e :other devem corresponder.',
    'size' => [
        'numeric' => 'Este registro deve ser :size.',
        'file' => 'Este registro deve ser :size kilobytes.',
        'string' => 'Este registro deve ser :size caracteres.',
        'array' => 'Este registro deve conter :size itens.',
    ],
    'starts_with' => 'Este registro deve começar com um dos seguintes valores: :values',
    'string' => 'Este registro deve ser uma string.',
    'timezone' => 'Este registro deve ser uma zona válida.',
    'unique' => 'Este registro já está sendo utilizado.',
    'uploaded' => 'Ocorreu uma falha no upload deste registro.',
    'uppercase' => 'Este registro deve conter letras maiúsculas.',
    'url' => 'Este registro tem um formato inválido.',
    'ulid' => 'Este registro deve ser um ULID válido.',
    'uuid' => 'Este registro deve ser um UUID válido.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [
        'address' => 'endereço',
        'age' => 'idade',
        'body' => 'conteúdo',
        'cell' => 'célula',
        'city' => 'cidade',
        'country' => 'país',
        'date' => 'data',
        'day' => 'dia',
        'excerpt' => 'resumo',
        'first_name' => 'primeiro nome',
        'gender' => 'gênero',
        'marital_status' => 'estado civil',
        'profession' => 'profissão',
        'nationality' => 'nacionalidade',
        'hour' => 'hora',
        'last_name' => 'sobrenome',
        'message' => 'mensagem',
        'minute' => 'minuto',
        'mobile' => 'celular',
        'month' => 'mês',
        'name' => 'nome',
        'zipcode' => 'cep',
        'company_name' => 'razão social',
        'neighborhood' => 'bairro',
        'number' => 'número',
        'password' => 'senha',
        'phone' => 'telefone',
        'second' => 'segundo',
        'sex' => 'sexo',
        'state' => 'estado',
        'street' => 'rua',
        'subject' => 'assunto',
        'text' => 'texto',
        'time' => 'hora',
        'title' => 'título',
        'username' => 'usuário',
        'year' => 'ano',
        'description' => 'descrição',
        'password_confirmation' => 'confirmação da senha',
        'current_password' => 'senha atual',
        'complement' => 'complemento',
        'modality' => 'modalidade',
        'category' => 'categoria',
        'blood_type' => 'tipo sanguíneo',
        'birth_date' => 'data de nascimento',
    ],

];
