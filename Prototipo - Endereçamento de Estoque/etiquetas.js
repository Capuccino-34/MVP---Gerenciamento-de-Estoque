// Funções para o gerador de etiquetas
function abrirModalEtiqueta() {
    document.getElementById('modalEtiqueta').classList.add('active');
    document.body.style.overflow = 'hidden';
    setTimeout(atualizarPreviewEtiqueta, 100);
}

function atualizarPreviewEtiqueta() {
    const letra = document.getElementById('etiquetaLetra').value;
    const numero = document.getElementById('etiquetaNumero').value || '0';
    const texto = letra + '-' + numero;
    
    // Atualiza o texto no SVG
    const textoEtiqueta = document.getElementById('textoEtiqueta');
    if (textoEtiqueta) {
        textoEtiqueta.textContent = texto;
    }
}

function gerarEtiquetaPDF() {
    try {
        // Verifica se o jsPDF está disponível
        if (typeof window.jspdf === 'undefined') {
            throw new Error('Biblioteca jsPDF não carregada');
        }

        const letra = document.getElementById('etiquetaLetra').value;
        if (!letra) {
            alert('Por favor, selecione uma letra.');
            return;
        }

        const numero = document.getElementById('etiquetaNumero').value;
        if (!numero) {
            alert('Por favor, digite um número.');
            return;
        }

        const texto = letra + ' - ' + numero;

        // Cria o documento PDF
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF({
            orientation: 'portrait',
            unit: 'mm',
            format: 'a4'
        });

        // Configurações da etiqueta
        pdf.setFillColor(0, 0, 0);
        pdf.setDrawColor(255, 255, 255);
        pdf.setLineWidth(0.5);

        // Desenha a etiqueta
        pdf.roundedRect(20, 40, 120, 40, 5, 5, 'FD');

        // Círculo branco
        pdf.setFillColor(255, 255, 255);
        pdf.circle(130, 60, 4, 'F');

        // Configura e adiciona o texto
        pdf.setTextColor(255, 255, 255);
        pdf.setFontSize(40);
        pdf.setFont('helvetica', 'bold');
        
        // Adiciona o texto centralizado
        pdf.text(texto, 80, 65, { align: 'center' });

        // Salva o PDF
        pdf.save('etiqueta-' + texto.replace(/\s/g, '_') + '.pdf');
        
        // Fecha o modal
        document.getElementById('modalEtiqueta').classList.remove('active');
        document.body.style.overflow = 'auto';
        
    } catch (error) {
        console.error('Erro ao gerar PDF:', error);
        alert('Ocorreu um erro ao gerar o PDF. Por favor, tente novamente.');
    }
}

// Adiciona os event listeners quando o documento carregar
document.addEventListener('DOMContentLoaded', function() {
    const etiquetaLetra = document.getElementById('etiquetaLetra');
    const etiquetaNumero = document.getElementById('etiquetaNumero');
    
    if (etiquetaLetra) {
        etiquetaLetra.addEventListener('change', atualizarPreviewEtiqueta);
    }
    
    if (etiquetaNumero) {
        etiquetaNumero.addEventListener('input', atualizarPreviewEtiqueta);
    }
});
