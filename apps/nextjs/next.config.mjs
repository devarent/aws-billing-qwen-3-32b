/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
  // Proxy ke API PHP lokal agar bisa dipanggil dari frontend tanpa CORS.
  async rewrites() {
    return [
      {
        source: '/api/php',
        destination: 'http://localhost:8081/api.php'
      },
      {
        source: '/api/php/:path*',
        destination: 'http://localhost:8081/api.php/:path*'
      }
    ];
  }
};

export default nextConfig;
