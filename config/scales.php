<?php

declare(strict_types=1);

/*
 * Escalas de rastreio de uso livre, nas versões traduzidas e validadas para o português do Brasil.
 * As respostas e a pontuação são cifradas no navegador; o servidor só conhece a escala e a data.
 * Ao alterar itens ou faixas, incremente a "version": ela é gravada junto com as respostas.
 *
 * Pontuação (calculada no navegador, patient-evolution.js):
 * - sem "subscales": soma das respostas, classificada em "ranges" (0 a "max");
 * - com "subscales": soma dos itens de cada subescala × "multiplier", classificada nos "ranges" da subescala.
 * "critical_item" (índice base 0): item que pede atenção imediata quando a resposta for maior que zero.
 * "reference_event": mostra o campo opcional de evento de referência (cifrado junto com as respostas).
 */

$frequency = [
    ['value' => 0, 'label' => 'Nenhuma vez'],
    ['value' => 1, 'label' => 'Vários dias'],
    ['value' => 2, 'label' => 'Mais da metade dos dias'],
    ['value' => 3, 'label' => 'Quase todos os dias'],
];

return [
    'phq9' => [
        'version' => 1,
        'short' => 'Sintomas depressivos',
        'instruction' => 'Durante as últimas 2 semanas, com que frequência você foi incomodado(a) por qualquer um dos problemas abaixo?',
        'items' => [
            'Pouco interesse ou pouco prazer em fazer as coisas',
            'Se sentir "para baixo", deprimido(a) ou sem perspectiva',
            'Dificuldade para pegar no sono ou permanecer dormindo, ou dormir mais do que de costume',
            'Se sentir cansado(a) ou com pouca energia',
            'Falta de apetite ou comendo demais',
            'Se sentir mal consigo mesmo(a) ou achar que é um fracasso ou que decepcionou sua família ou a si mesmo(a)',
            'Dificuldade para se concentrar nas coisas, como ler o jornal ou ver televisão',
            'Lentidão para se movimentar ou falar, a ponto de outras pessoas perceberem, ou o oposto: estar tão agitado(a) ou inquieto(a) que fica andando de um lado para o outro muito mais do que de costume',
            'Pensar em se ferir de alguma maneira ou que seria melhor estar morto(a)',
        ],
        'options' => $frequency,
        'max' => 27,
        'ranges' => [
            ['min' => 0, 'max' => 4, 'label' => 'Mínimo', 'level' => 0],
            ['min' => 5, 'max' => 9, 'label' => 'Leve', 'level' => 1],
            ['min' => 10, 'max' => 14, 'label' => 'Moderado', 'level' => 2],
            ['min' => 15, 'max' => 19, 'label' => 'Moderadamente grave', 'level' => 3],
            ['min' => 20, 'max' => 27, 'label' => 'Grave', 'level' => 4],
        ],
        // Índice (base 0) do item que pede atenção imediata quando a resposta for maior que zero.
        'critical_item' => 8,
        'critical_label' => 'Item 9',
        'critical_message' => 'A resposta ao item 9 indica pensamentos de morte ou de se ferir. Vale acolher e explorar esse ponto ainda nesta sessão e considerar o plano de cuidado e a rede de apoio.',
        'source' => 'Patient Health Questionnaire (PHQ-9), Kroenke, Spitzer & Williams (2001). Versão em português validada no Brasil.',
    ],
    'gad7' => [
        'version' => 1,
        'short' => 'Sintomas de ansiedade',
        'instruction' => 'Durante as últimas 2 semanas, com que frequência você foi incomodado(a) pelos problemas abaixo?',
        'items' => [
            'Sentir-se nervoso(a), ansioso(a) ou muito tenso(a)',
            'Não ser capaz de impedir ou de controlar as preocupações',
            'Preocupar-se muito com diversas coisas',
            'Dificuldade para relaxar',
            'Ficar tão agitado(a) que se torna difícil permanecer sentado(a)',
            'Ficar facilmente aborrecido(a) ou irritado(a)',
            'Sentir medo como se algo horrível fosse acontecer',
        ],
        'options' => $frequency,
        'max' => 21,
        'ranges' => [
            ['min' => 0, 'max' => 4, 'label' => 'Mínimo', 'level' => 0],
            ['min' => 5, 'max' => 9, 'label' => 'Leve', 'level' => 1],
            ['min' => 10, 'max' => 14, 'label' => 'Moderado', 'level' => 2],
            ['min' => 15, 'max' => 21, 'label' => 'Grave', 'level' => 4],
        ],
        'critical_item' => null,
        'critical_label' => null,
        'critical_message' => null,
        'source' => 'Generalized Anxiety Disorder 7 (GAD-7), Spitzer, Kroenke, Williams & Löwe (2006). Versão em português validada no Brasil.',
    ],
    'dass21' => [
        'version' => 1,
        'short' => 'Depressão, ansiedade e estresse',
        // Instrução original adaptada ao formato digital ("circule o número" → "marque a opção").
        'instruction' => 'Por favor, leia cuidadosamente cada uma das afirmações abaixo e marque a opção que indique o quanto ela se aplicou a você durante a última semana.',
        'items' => [
            'Achei difícil me acalmar',
            'Senti minha boca seca',
            'Não consegui vivenciar nenhum sentimento positivo',
            'Tive dificuldade em respirar em alguns momentos (ex. respiração ofegante, falta de ar, sem ter feito nenhum esforço físico)',
            'Achei difícil ter iniciativa para fazer as coisas',
            'Tive a tendência de reagir de forma exagerada às situações',
            'Senti tremores (ex. nas mãos)',
            'Senti que estava sempre nervoso',
            'Preocupei-me com situações em que eu pudesse entrar em pânico e parecesse ridículo (a)',
            'Senti que não tinha nada a desejar',
            'Senti-me agitado',
            'Achei difícil relaxar',
            'Senti-me depressivo (a) e sem ânimo',
            'Fui intolerante com as coisas que me impediam de continuar o que eu estava fazendo',
            'Senti que ia entrar em pânico',
            'Não consegui me entusiasmar com nada',
            'Senti que não tinha valor como pessoa',
            'Senti que estava um pouco emotivo/sensível demais',
            'Sabia que meu coração estava alterado mesmo não tendo feito nenhum esforço físico (ex. aumento da frequência cardíaca, disritmia cardíaca)',
            'Senti medo sem motivo',
            'Senti que a vida não tinha sentido',
        ],
        'options' => [
            ['value' => 0, 'label' => 'Não se aplicou de maneira alguma'],
            ['value' => 1, 'label' => 'Aplicou-se em algum grau, ou por pouco de tempo'],
            ['value' => 2, 'label' => 'Aplicou-se em um grau considerável, ou por uma boa parte do tempo'],
            ['value' => 3, 'label' => 'Aplicou-se muito, ou na maioria do tempo'],
        ],
        // Sem pontuação total: cada subescala é somada e multiplicada por 2 (equivalência com o DASS-42).
        'max' => null,
        'ranges' => [],
        'subscales' => [
            [
                'key' => 'depression',
                'label' => 'Depressão',
                'items' => [2, 4, 9, 12, 15, 16, 20],
                'multiplier' => 2,
                'max' => 42,
                'ranges' => [
                    ['min' => 0, 'max' => 9, 'label' => 'Normal', 'level' => 0],
                    ['min' => 10, 'max' => 13, 'label' => 'Leve', 'level' => 1],
                    ['min' => 14, 'max' => 20, 'label' => 'Moderado', 'level' => 2],
                    ['min' => 21, 'max' => 27, 'label' => 'Grave', 'level' => 3],
                    ['min' => 28, 'max' => 42, 'label' => 'Extremamente grave', 'level' => 4],
                ],
            ],
            [
                'key' => 'anxiety',
                'label' => 'Ansiedade',
                'items' => [1, 3, 6, 8, 14, 18, 19],
                'multiplier' => 2,
                'max' => 42,
                'ranges' => [
                    ['min' => 0, 'max' => 7, 'label' => 'Normal', 'level' => 0],
                    ['min' => 8, 'max' => 9, 'label' => 'Leve', 'level' => 1],
                    ['min' => 10, 'max' => 14, 'label' => 'Moderado', 'level' => 2],
                    ['min' => 15, 'max' => 19, 'label' => 'Grave', 'level' => 3],
                    ['min' => 20, 'max' => 42, 'label' => 'Extremamente grave', 'level' => 4],
                ],
            ],
            [
                'key' => 'stress',
                'label' => 'Estresse',
                'items' => [0, 5, 7, 10, 11, 13, 17],
                'multiplier' => 2,
                'max' => 42,
                'ranges' => [
                    ['min' => 0, 'max' => 14, 'label' => 'Normal', 'level' => 0],
                    ['min' => 15, 'max' => 18, 'label' => 'Leve', 'level' => 1],
                    ['min' => 19, 'max' => 25, 'label' => 'Moderado', 'level' => 2],
                    ['min' => 26, 'max' => 33, 'label' => 'Grave', 'level' => 3],
                    ['min' => 34, 'max' => 42, 'label' => 'Extremamente grave', 'level' => 4],
                ],
            ],
        ],
        'critical_item' => null,
        'critical_label' => null,
        'critical_message' => null,
        'source' => 'Depression Anxiety Stress Scales (DASS-21), Lovibond & Lovibond (1995), domínio público. Versão traduzida e validada para o português do Brasil por Vignola & Tucci (UNIFESP, 2014). Faixas de gravidade do manual da DASS.',
    ],
    'pcl5' => [
        'version' => 1,
        'short' => 'Estresse pós-traumático',
        'instruction' => 'A seguir é apresentada uma lista de dificuldades que as pessoas podem enfrentar após vivenciar uma experiência muito traumatizante. Mantendo o seu pior evento em mente, leia cuidadosamente cada uma das dificuldades e indique o quanto você tem se sentido incomodado por essa dificuldade no último mês.',
        'items' => [
            'Lembranças repetidas, perturbadoras e involuntárias da experiência traumatizante.',
            'Sonhos repetidos e perturbadores referentes à experiência traumatizante.',
            'De repente, se sentir ou agir como se a experiência traumatizante estivesse realmente acontecendo de novo (como se você estivesse lá de volta revivendo a situação).',
            'Sentir-se muito perturbado quando algo lhe faz lembrar da experiência traumatizante.',
            'Apresentar reações físicas intensas quando algo lhe faz lembrar da experiência traumatizante (por exemplo, coração bater forte, dificuldades para respirar, suor excessivo).',
            'Evitar lembranças, pensamentos ou sentimentos relacionados à experiência traumatizante.',
            'Evitar algo ou alguém que lembre você da experiência traumatizante (por exemplo, pessoas, lugares, conversas, atividades, objetos ou situações).',
            'Dificuldades de se lembrar de partes importantes da experiência traumatizante.',
            'Ter fortes crenças negativas sobre si mesmo, sobre outras pessoas ou sobre o mundo (por exemplo, ter pensamentos como: eu sou ruim, há algo muito errado comigo, não se pode confiar em ninguém, o mundo é um lugar muito perigoso).',
            'Culpar a si mesmo ou a outra pessoa pela experiência traumatizante ou pelo que aconteceu depois de tal experiência.',
            'Ter fortes sentimentos negativos, tais como medo, horror, raiva, culpa ou vergonha',
            'Perder o interesse em atividades que você costumava gostar.',
            'Sentir-se distante ou isolado das outras pessoas.',
            'Dificuldades para experimentar sentimentos positivos (por exemplo, ser incapaz de sentir felicidade ou de ter sentimentos afetuosos pelas pessoas próximas a você).',
            'Comportamento irritável, explosões de raiva, ou agir de forma agressiva.',
            'Arriscar-se muito ou fazer coisas que podem causar algum mal a você.',
            'Estar "superalerta" ou hipervigilante.',
            'Sentir-se sobressaltado ou assustar-se facilmente.',
            'Ter dificuldades para se concentrar.',
            'Dificuldades para “pegar no sono” ou para permanecer dormindo.',
        ],
        'options' => [
            ['value' => 0, 'label' => 'Absolutamente nada'],
            ['value' => 1, 'label' => 'Um pouco'],
            ['value' => 2, 'label' => 'Moderadamente'],
            ['value' => 3, 'label' => 'Muito'],
            ['value' => 4, 'label' => 'Extremamente'],
        ],
        'max' => 80,
        // A PCL-5 não tem faixas de gravidade, só ponto de corte (31 a 33 sugere TEPT provável, National Center for PTSD).
        'ranges' => [
            ['min' => 0, 'max' => 32, 'label' => 'Abaixo do ponto de corte', 'level' => 0],
            ['min' => 33, 'max' => 80, 'label' => 'Acima do ponto de corte', 'level' => 3],
        ],
        'critical_item' => null,
        'critical_label' => null,
        'critical_message' => null,
        'reference_event' => true,
        'source' => 'PTSD Checklist for DSM-5 (PCL-5), Weathers et al. (2013), National Center for PTSD, domínio público. Tradução e adaptação para o Brasil por Lima, Vasconcelos, Berger, Kristensen, Nascimento, Figueira & Mendlowicz (2016). Ponto de corte de 33 sugere TEPT provável e pede avaliação clínica.',
    ],
];
