<?php
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Rota Otimizada</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        #map { height: 500px; width: 100%; margin-top: 20px; display: none; }
    </style>
</head>
<body>
    <h2>Informe até 10 endereços (um por linha)</h2>
    <form id="rotaForm">
        <textarea name="enderecos" id="enderecos" rows="10" cols="60" placeholder="Ex: Avenida Paulista, São Paulo"></textarea><br><br>
        <button type="submit">Calcular Rota</button>
    </form>

    <div id="resultado" style="margin-top:20px;"></div>
    <div id="map">a</div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        let map;

        document.getElementById("rotaForm").addEventListener("submit", async function(e) {
            e.preventDefault();
            const linhas = document.getElementById("enderecos").value.trim().split("\n");
            if (linhas.length < 2) {
                alert("Informe pelo menos dois endereços.");
                return;
            }

            const resposta = await fetch("api_rota.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ enderecos: linhas })
            });

            const dados = await resposta.json();

            if (dados.google_maps_url && dados.paradas) {
                document.getElementById("resultado").innerHTML = `
                    <h3>Rota Otimizada</h3>
                    <p><strong>Distância total:</strong> ${dados.distancia_total_km} km</p>
                    <p><strong>Duração estimada:</strong> ${dados.duracao_total_min} minutos</p>
                    <a href="${dados.google_maps_url}" target="_blank">Abrir no Google Maps</a>
                `;
                desenharMapa(dados.paradas);
            } else {
                document.getElementById("resultado").innerText = "Erro ao gerar rota.";
            }
        });

        function desenharMapa(coordenadas) {
            if (map) map.remove();
            document.getElementById("map").style.display = 'block';
            map = L.map('map').setView([coordenadas[0][1], coordenadas[0][0]], 7);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap'
            }).addTo(map);

            const pontos = coordenadas.map(p => [p[1], p[0]]);
            pontos.forEach((p, i) => {
                L.marker(p).addTo(map).bindPopup("Parada " + (i + 1));
            });

            L.polyline(pontos, { color: 'blue' }).addTo(map);
            map.fitBounds(pontos);
        }
    </script>
</body>
</html>