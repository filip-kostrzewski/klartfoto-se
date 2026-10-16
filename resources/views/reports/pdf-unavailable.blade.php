<!doctype html>
<html lang="sv">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PDF tillfälligt otillgänglig · Klartfoto</title>
    <style>
        body {
            background: #f4f5f0;
            color: #17211b;
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            min-height: 100vh;
        }

        main {
            background: #fff;
            border: 1px solid #d5ddd5;
            border-radius: 12px;
            margin: 12vh auto;
            max-width: 32rem;
            padding: 2rem;
        }

        h1 {
            font-size: 1.5rem;
        }

        a {
            color: #17211b;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <main>
        <p>Klartfoto · Fotorapport</p>
        <h1>PDF kan inte skapas just nu</h1>
        <p>Försök igen om en stund. Rapporten har inte ändrats.</p>
        <a href="{{ $reportUrl }}">Tillbaka till rapporten</a>
    </main>
</body>
</html>
