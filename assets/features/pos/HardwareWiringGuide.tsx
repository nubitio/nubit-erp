export function HardwareWiringGuide() {
  return (
    <div className="hardware-wiring-guide">
      <p className="hardware-wiring-guide__intro">
        La balanza se conecta por <strong>RS-232</strong> al puerto serie de la PC. En equipos
        modernos casi siempre se usa un adaptador <strong>USB ↔ RS-232</strong>.
      </p>

      <section className="hardware-wiring-guide__section">
        <h4>Materiales</h4>
        <ul>
          <li>Adaptador USB a RS-232 (chip FTDI, Prolific o CH340)</li>
          <li>Cable RS-232 hembra–hembra o según el conector de la balanza Excell</li>
          <li>
            PC con el agente <code>efact-hardware-agent</code> instalado
          </li>
        </ul>
      </section>

      <section className="hardware-wiring-guide__section">
        <h4>Conexión eléctrica (DB9, vista del conector)</h4>
        <pre className="hardware-wiring-guide__diagram" aria-label="Diagrama de cableado RS-232">
          {`Balanza RS-232          Adaptador USB-Serial          PC
──────────────          ─────────────────────          ───
  TX (envía) ───────────────────────────────► RX
  RX (recibe) ◄────────────────────────────── TX
  GND ───────────────────────────────────── GND

⚠ Cruza TX↔RX. No conectes ambos TX juntos.
⚠ La balanza tiene alimentación propia (no sale del USB).`}
        </pre>
      </section>

      <section className="hardware-wiring-guide__section">
        <h4>Excell (stream continuo)</h4>
        <ul>
          <li>
            Protocolo recomendado: <strong>excell</strong> (9600, 8N1)
          </li>
          <li>La balanza envía líneas de peso de forma continua, sin botón</li>
          <li>Coloca el producto y espera que el peso se estabilice en la pestaña Balanza</li>
          <li>
            Si el peso no aparece, prueba <strong>generic</strong> y revisa el campo <em>raw</em>
          </li>
        </ul>
      </section>

      <section className="hardware-wiring-guide__section">
        <h4>Verificación rápida</h4>
        <ol>
          <li>Conecta cable y adaptador USB</li>
          <li>En Balanza → actualiza puertos y selecciona el nuevo COM / tty / cu.usbserial</li>
          <li>Activa la balanza y guarda configuración</li>
          <li>
            El indicador debe pasar a <strong>Conectada</strong> y mostrar peso en vivo
          </li>
        </ol>
      </section>

      <section className="hardware-wiring-guide__section hardware-wiring-guide__section--note">
        <h4>Problemas frecuentes</h4>
        <ul>
          <li>
            <strong>Puerto no aparece:</strong> instala el driver del adaptador USB-serial y
            reconecta.
          </li>
          <li>
            <strong>Conectada pero sin peso:</strong> revisa cruce TX/RX y que GND esté unido.
          </li>
          <li>
            <strong>Caracteres basura:</strong> confirma baud rate 9600 y protocolo correcto.
          </li>
        </ul>
      </section>
    </div>
  );
}
