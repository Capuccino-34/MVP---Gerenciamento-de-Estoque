<!DOCTYPE html>
<html>
<head>
    <title>Gerador de Etiquetas</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>
<body>
    <h2>Gerador de Etiquetas</h2>
    
    <form>
        <label>Letra:</label>
        <select id="letra">
            <option value="L">L</option>
            <option value="Z">Z</option>
            <option value="P">P</option>
        </select>
        <br><br>
        
        <label>Número:</label>
        <input type="number" id="numero" placeholder="Digite apenas números">
        <br><br>
        
        <button type="button" onclick="gerarPDF()">Criar PDF</button>
    </form>
    
    <br>
    <div id="preview">
        <h3>Preview:</h3>
        <svg width="250" height="120" id="etiqueta">
            <!-- Etiqueta completa com bordas arredondadas -->
            <path d="M15,20 L170,20 Q180,20 180,30 L180,50 L200,60 L180,70 L180,90 Q180,100 170,100 L15,100 Q5,100 5,90 L5,30 Q5,20 15,20 Z" 
                  fill="black" stroke="white" stroke-width="3"/>
            <!-- Círculo do buraco -->
            <circle cx="185" cy="30" r="8" fill="white"/>
            <!-- Texto -->
            <text x="95" y="65" fill="white" font-family="Arial, sans-serif" font-size="22" font-weight="bold" text-anchor="middle" id="textoEtiqueta">P-11</text>
        </svg>
    </div>

    <script>
        // Atualiza o preview quando os valores mudam
        document.getElementById('letra').addEventListener('change', atualizarPreview);
        document.getElementById('numero').addEventListener('input', atualizarPreview);
        
        function atualizarPreview() {
            const letra = document.getElementById('letra').value;
            const numero = document.getElementById('numero').value;
            const texto = letra + '-' + (numero || '0');
            document.getElementById('textoEtiqueta').textContent = texto;
        }
        
        function gerarPDF() {
            const { jsPDF } = window.jspdf;
            const pdf = new jsPDF();
            
            const letra = document.getElementById('letra').value;
            const numero = document.getElementById('numero').value || '0';
            const texto = letra + ' - ' + numero;
            
            // Desenha a etiqueta completa no PDF
            pdf.setFillColor(0, 0, 0); // Preto
            pdf.setDrawColor(255, 255, 255); // Borda branca
            pdf.setLineWidth(1);
            
            // Corpo principal da etiqueta (retângulo com cantos arredondados)
            pdf.roundedRect(20, 40, 120, 40, 5, 5, 'FD');
            
            // Buraco da etiqueta (círculo branco)
            pdf.setFillColor(255, 255, 255);
            pdf.circle(130, 60, 4, 'F');
            
            // Texto em branco no centro
            pdf.setTextColor(255, 255, 255);
            pdf.setFontSize(40);
            pdf.setFont(undefined, 'bold');
            pdf.text(texto, 80, 65, { align: 'center' });
            
            // Salva o PDF
            pdf.save('etiqueta-' + texto + '.pdf');
        }
        
        // Inicializa o preview
        atualizarPreview();
    </script>
</body>
</html>