export default function Home() {
  return (
    <main className="container">
      <h1>AI Billing &amp; Cloud Hub — Next.js</h1>
      <p>Frontend Next.js siap dikembangkan dan terhubung ke API PHP.</p>

      <div className="card">
        <h2>Menjalankan</h2>
        <pre>
{`npm install
npm run dev`}
        </pre>
        <p>
          Buka <a href="http://localhost:3000">http://localhost:3000</a>
        </p>
      </div>

      <div className="card">
        <h2>Integrasi API</h2>
        <p>
          API PHP tersedia melalui proxy <code>/api/php?action=status</code>{' '}
          (meneruskan ke <code>http://localhost:8081/api.php</code>).
        </p>
        <p>
          Dashboard utama: <a href="http://localhost:8081">http://localhost:8081</a>
        </p>
      </div>
    </main>
  );
}
