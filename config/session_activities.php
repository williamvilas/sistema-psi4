<?php

declare(strict_types=1);

/**
 * Biblioteca de dinâmicas de apoio da sessão de vídeo: recursos de apoio à conversa, não testes psicológicos.
 * Sem pontuação, faixa, ponto de corte ou interpretação. Os textos gravados no registro são os daqui, no momento do uso.
 * Conteúdo próprio: não copiar baralhos comerciais nem o desenho de rodas publicadas.
 */
return [
    'disclaimer' => 'Recursos de apoio à conversa. Não são testes psicológicos, não medem nem diagnosticam. A condução e a interpretação são suas.',
    'patient_notice' => 'Esta é uma atividade para apoiar a conversa, não um teste. Você pode pular o que quiser. O que aparecer aqui pode ser guardado de forma cifrada no seu prontuário.',

    'activities' => [
        'emotion_wheel' => [
            'title' => 'Roda das emoções',
            'description' => 'O paciente escolhe uma emoção na roda e conta o que está sentindo, onde sente no corpo e o que pode ter provocado.',
            'config' => [
                'emotions' => [
                    'Alegria', 'Calma', 'Gratidão', 'Esperança', 'Tristeza', 'Saudade',
                    'Medo', 'Ansiedade', 'Raiva', 'Frustração', 'Culpa', 'Vergonha',
                ],
                'questions' => [
                    'feeling' => 'O que estou sentindo?',
                    'body' => 'Onde sinto isso no corpo?',
                    'cause' => 'O que pode ter provocado?',
                ],
                'intensity' => 'Qual a intensidade, de 0 a 10?',
            ],
        ],
        'thermometer' => [
            'title' => 'Termômetro emocional',
            'description' => 'O paciente posiciona um marcador de 0 a 10 para representar como está se sentindo agora.',
            'config' => [
                'question' => 'Como você está se sentindo agora, de 0 a 10?',
                'comment' => 'Quer comentar algo?',
            ],
        ],
        'control_circles' => [
            'title' => 'O que está no meu controle?',
            'description' => 'O paciente organiza situações em três áreas: o que controla, o que pode influenciar e o que não controla.',
            'config' => [
                'areas' => [
                    'control' => 'Está sob meu controle',
                    'influence' => 'Posso influenciar',
                    'outside' => 'Não está sob meu controle',
                ],
            ],
        ],
        'question_cards' => [
            'title' => 'Cartas de perguntas',
            'description' => 'Cartas sorteadas na tela. Você escolhe as categorias e o nível de profundidade.',
            'config' => [
                'categories' => [
                    'self' => 'Sobre mim',
                    'relations' => 'Relações',
                    'present' => 'Momento atual',
                    'future' => 'Mudanças',
                ],
                'levels' => [
                    'light' => 'Leve',
                    'medium' => 'Médio',
                    'deep' => 'Profundo',
                ],
            ],
            // Só vai para o navegador da psicóloga: cada carta chega ao paciente apenas depois do sorteio.
            'deck' => [
                ['category' => 'self', 'level' => 'light', 'text' => 'O que costuma melhorar o seu dia?'],
                ['category' => 'self', 'level' => 'light', 'text' => 'Do que você gosta em si?'],
                ['category' => 'self', 'level' => 'medium', 'text' => 'O que você gostaria que as pessoas entendessem sobre você?'],
                ['category' => 'self', 'level' => 'deep', 'text' => 'O que é mais difícil de dizer em voz alta?'],
                ['category' => 'relations', 'level' => 'light', 'text' => 'Com quem você se sente à vontade?'],
                ['category' => 'relations', 'level' => 'medium', 'text' => 'Do que você sente falta?'],
                ['category' => 'relations', 'level' => 'medium', 'text' => 'Em qual relação você gostaria de se sentir mais ouvido ou ouvida?'],
                ['category' => 'relations', 'level' => 'deep', 'text' => 'O que você ainda gostaria de dizer a alguém?'],
                ['category' => 'present', 'level' => 'light', 'text' => 'Como foi a sua semana?'],
                ['category' => 'present', 'level' => 'medium', 'text' => 'O que tem ocupado seus pensamentos?'],
                ['category' => 'present', 'level' => 'medium', 'text' => 'O que tem tirado a sua energia?'],
                ['category' => 'present', 'level' => 'deep', 'text' => 'O que você tem evitado olhar?'],
                ['category' => 'future', 'level' => 'light', 'text' => 'O que você gostaria de fazer mais vezes?'],
                ['category' => 'future', 'level' => 'medium', 'text' => 'O que você gostaria de mudar?'],
                ['category' => 'future', 'level' => 'medium', 'text' => 'Qual pequeno passo parece possível agora?'],
                ['category' => 'future', 'level' => 'deep', 'text' => 'Quem você gostaria de ser daqui a alguns anos?'],
            ],
        ],
        'life_timeline' => [
            'title' => 'Minha linha do tempo',
            'description' => 'O paciente constrói uma linha do tempo com acontecimentos importantes, conquistas, desafios e mudanças.',
            'config' => [
                'types' => [
                    'important' => 'Acontecimento importante',
                    'achievement' => 'Conquista',
                    'challenge' => 'Desafio',
                    'change' => 'Mudança',
                ],
            ],
        ],
    ],
];
