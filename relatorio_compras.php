<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['logado']) || empty($_SESSION['id_cliente'])) {
    header('Location: login.php');
    exit;
}

function conectar()
{
    $local_server = "DESKTOP-54EB1G4\\SQLEXPRESS";
    $usuario_server = "superclick";
    $senha_server = "teste";
    $banco_de_dados = "SUPERCLICK";

    try {
        $dsn = "sqlsrv:server=$local_server;database=$banco_de_dados;Encrypt=true;TrustServerCertificate=true";
        $pdo = new PDO($dsn, $usuario_server, $senha_server);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (Exception $erro) {
        die("ATENÇÃO - ERRO NA CONEXÃO: " . $erro->getMessage());
    }
}

$pdo = conectar();
$id_cliente = (int) $_SESSION['id_cliente'];

$sqlCliente = $pdo->prepare(
    "SELECT Id_Cliente, Nome, CPF, Email
     FROM Cliente
     WHERE Id_Cliente = :id"
);
$sqlCliente->bindValue(":id", $id_cliente, PDO::PARAM_INT);
$sqlCliente->execute();
$cliente = $sqlCliente->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    header('Location: login.php');
    exit;
}

$inicioMes = date('Y-m-01');
$inicioProximoMes = date('Y-m-01', strtotime('+1 month'));

$meses = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];
$mesAno = $meses[(int) date('n')] . ' de ' . date('Y');

$itens = [];
$categorias = [];
$totalMes = 0;

try {
    $sqlItens = $pdo->prepare(
        "SELECT
            p.Id_Pedido,
            p.Data_Pedido,
            pi.Id_Pedido_Item,
            pr.Nome AS Produto,
            pr.Marca,
            c.Nome AS Categoria,
            pi.Quantidade,
            pi.Preco_Unitario,
            (pi.Preco_Unitario * pi.Quantidade) AS Subtotal
         FROM Pedido p
         INNER JOIN Pedido_Item pi ON p.Id_Pedido = pi.Id_Pedido
         INNER JOIN Produto pr ON pi.Id_Produto = pr.Id_Produto
         INNER JOIN Categoria c ON pr.Id_Categoria = c.Id_Categoria
         WHERE p.Id_Cliente = :id_cliente
           AND p.Data_Pedido >= :inicio_mes
           AND p.Data_Pedido < :inicio_proximo_mes
           AND LOWER(LTRIM(RTRIM(p.Status_Pedido))) <> 'cancelado'
         ORDER BY p.Data_Pedido DESC, p.Id_Pedido DESC, pi.Id_Pedido_Item ASC"
    );

    $sqlItens->bindValue(":id_cliente", $id_cliente, PDO::PARAM_INT);
    $sqlItens->bindValue(":inicio_mes", $inicioMes);
    $sqlItens->bindValue(":inicio_proximo_mes", $inicioProximoMes);
    $sqlItens->execute();

    $itens = $sqlItens->fetchAll(PDO::FETCH_ASSOC);

    foreach ($itens as $item) {
        $subtotal = (float) $item['Subtotal'];
        $totalMes += $subtotal;

        $categoria = $item['Categoria'];
        if (!isset($categorias[$categoria])) {
            $categorias[$categoria] = 0;
        }
        $categorias[$categoria] += $subtotal;
    }
} catch (Exception $erro) {
    $itens = [];
    $categorias = [];
    $totalMes = 0;
}

arsort($categorias);
$topCategorias = array_slice($categorias, 0, 8, true);
$totalTopCategorias = array_sum($topCategorias);

$cores = [
    '#166534', '#2563eb', '#ca8a04', '#dc2626',
    '#7c3aed', '#0891b2', '#ea580c', '#475569'
];

$fatias = [];
$inicio = 0;
$corIndex = 0;

foreach ($topCategorias as $categoria => $valor) {
    $percentual = $totalTopCategorias > 0
        ? ($valor / $totalTopCategorias) * 100
        : 0;

    $fim = $inicio + $percentual;

    $fatias[] = [
        'categoria' => $categoria,
        'valor' => $valor,
        'percentual' => $percentual,
        'inicio' => $inicio,
        'fim' => $fim,
        'cor' => $cores[$corIndex % count($cores)]
    ];

    $inicio = $fim;
    $corIndex++;
}

$grafico = 'conic-gradient(';
foreach ($fatias as $i => $fatia) {
    if ($i > 0) {
        $grafico .= ', ';
    }

    $grafico .= $fatia['cor'] . ' ' .
        number_format($fatia['inicio'], 3, '.', '') . '% ' .
        number_format($fatia['fim'], 3, '.', '') . '%';
}
$grafico .= ')';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SuperClick - Relatório de Compras</title>

    <style>
        @page {
            size: A4;
            margin: 14mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f1f5f9;
            color: #1e293b;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
        }

        .relatorio {
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            padding: 18mm;
            background: #fff;
            box-shadow: 0 3px 15px rgba(0,0,0,.10);
        }

        .botoes {
            width: 210mm;
            margin: 15px auto;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .botao {
            border: 0;
            border-radius: 7px;
            padding: 10px 15px;
            cursor: pointer;
            font-weight: 700;
        }

        .botao-imprimir {
            background: #166534;
            color: #fff;
        }

        .botao-fechar {
            background: #e2e8f0;
            color: #0f172a;
        }

        .cabecalho {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #166534;
            padding-bottom: 14px;
            margin-bottom: 18px;
        }

        .logo {
            color: #166534;
            font-size: 26px;
            font-weight: 800;
            margin: 0 0 5px;
        }

        .titulo {
            margin: 0;
            font-size: 21px;
        }

        .periodo {
            margin: 6px 0 0;
            color: #64748b;
        }

        .dados-cliente {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 25px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 13px;
            margin-bottom: 20px;
        }

        .campo strong {
            display: block;
            font-size: 11px;
            color: #64748b;
            margin-bottom: 3px;
            text-transform: uppercase;
        }

        .campo span {
            font-size: 14px;
        }

        .secao-titulo {
            font-size: 16px;
            margin: 20px 0 10px;
            color: #0f172a;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #166534;
            color: #fff;
            padding: 8px 7px;
            text-align: left;
            font-size: 11px;
        }

        td {
            border-bottom: 1px solid #e2e8f0;
            padding: 7px;
            vertical-align: top;
        }

        .direita {
            text-align: right;
        }

        .total {
            display: flex;
            justify-content: flex-end;
            margin-top: 12px;
            font-size: 16px;
            font-weight: 800;
        }

        .total span {
            margin-left: 8px;
            color: #166534;
        }

        .grafico-secao {
            margin-top: 25px;
            page-break-inside: avoid;
        }

        .grafico-area {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 35px;
            margin-top: 15px;
        }

        .pizza {
            width: 185px;
            height: 185px;
            border-radius: 50%;
            background: <?= htmlspecialchars($grafico, ENT_QUOTES, 'UTF-8') ?>;
            flex: 0 0 auto;
        }

        .legenda {
            min-width: 260px;
        }

        .legenda-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 7px 0;
        }

        .legenda-cor {
            width: 13px;
            height: 13px;
            border-radius: 3px;
            flex: 0 0 auto;
        }

        .legenda-texto {
            flex: 1;
        }

        .legenda-valor {
            font-weight: 700;
        }

        .observacao {
            margin-top: 15px;
            color: #64748b;
            font-size: 10px;
        }

        .vazio {
            text-align: center;
            padding: 30px 10px;
            color: #64748b;
            border: 1px dashed #cbd5e1;
        }

        @media print {
            body {
                background: #fff;
            }

            .relatorio {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }

            .botoes {
                display: none;
            }

            .pizza,
            th {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        @media (max-width: 800px) {
            .relatorio,
            .botoes {
                width: 100%;
            }

            .relatorio {
                margin: 0;
                padding: 20px;
            }

            .dados-cliente {
                grid-template-columns: 1fr;
            }

            .grafico-area {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

<div class="botoes">
    <button class="botao botao-fechar" type="button" onclick="window.close();">
        Fechar
    </button>

    <button class="botao botao-imprimir" type="button" onclick="window.print();">
        Imprimir / Salvar PDF
    </button>
</div>

<main class="relatorio">

    <header class="cabecalho">
        <div>
            <h1 class="logo">SuperClick</h1>
            <h2 class="titulo">Relatório de Compras</h2>
            <p class="periodo">
                Histórico de compras — <?= htmlspecialchars($mesAno, ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>

        <div>
            <strong>Data do relatório</strong><br>
            <?= date('d/m/Y') ?>
        </div>
    </header>

    <section class="dados-cliente">
        <div class="campo">
            <strong>Nome</strong>
            <span><?= htmlspecialchars($cliente['Nome'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <div class="campo">
            <strong>CPF</strong>
            <span><?= htmlspecialchars($cliente['CPF'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </section>

    <h2 class="secao-titulo">Compras do mês</h2>

    <?php if (count($itens) > 0) { ?>

        <table>
            <thead>
                <tr>
                    <th>Pedido</th>
                    <th>Data</th>
                    <th>Produto</th>
                    <th>Categoria</th>
                    <th>Qtd.</th>
                    <th class="direita">Valor unit.</th>
                    <th class="direita">Subtotal</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($itens as $item) { ?>
                    <tr>
                        <td>#<?= (int) $item['Id_Pedido'] ?></td>
                        <td><?= date('d/m/Y', strtotime($item['Data_Pedido'])) ?></td>
                        <td>
                            <?= htmlspecialchars($item['Produto'], ENT_QUOTES, 'UTF-8') ?>
                            <?php if (!empty($item['Marca'])) { ?>
                                <br>
                                <small><?= htmlspecialchars($item['Marca'], ENT_QUOTES, 'UTF-8') ?></small>
                            <?php } ?>
                        </td>
                        <td><?= htmlspecialchars($item['Categoria'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= (int) $item['Quantidade'] ?></td>
                        <td class="direita">
                            R$ <?= number_format((float) $item['Preco_Unitario'], 2, ',', '.') ?>
                        </td>
                        <td class="direita">
                            R$ <?= number_format((float) $item['Subtotal'], 2, ',', '.') ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>

        <div class="total">
            Total gasto no mês:
            <span>R$ <?= number_format($totalMes, 2, ',', '.') ?></span>
        </div>

    <?php } else { ?>

        <div class="vazio">
            Nenhuma compra realizada neste mês.
        </div>

    <?php } ?>

    <?php if (count($topCategorias) > 0) { ?>

        <section class="grafico-secao">
            <h2 class="secao-titulo">8 maiores gastos por categoria</h2>

            <p>
                Distribuição dos gastos entre as oito categorias com maior
                valor acumulado no mês.
            </p>

            <div class="grafico-area">

                <div class="pizza"></div>

                <div class="legenda">
                    <?php foreach ($fatias as $fatia) { ?>
                        <div class="legenda-item">
                            <span
                                class="legenda-cor"
                                style="background: <?= htmlspecialchars($fatia['cor'], ENT_QUOTES, 'UTF-8') ?>;">
                            </span>

                            <span class="legenda-texto">
                                <?= htmlspecialchars($fatia['categoria'], ENT_QUOTES, 'UTF-8') ?>
                            </span>

                            <span class="legenda-valor">
                                R$ <?= number_format($fatia['valor'], 2, ',', '.') ?>
                            </span>
                        </div>
                    <?php } ?>
                </div>

            </div>
        </section>

    <?php } ?>

    <p class="observacao">
        O relatório considera somente compras do mês atual que não estejam
        canceladas. Os valores dos produtos são os valores registrados no
        momento da compra.
    </p>

</main>


</body>
</html>
