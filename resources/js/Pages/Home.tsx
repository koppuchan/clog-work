import { Head } from '@inertiajs/react';

interface HomeProps {
  message: string;
}

export default function Home({ message }: HomeProps) {
  return (
    <>
      <Head title="Home" />

      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="max-w-md w-full p-8 bg-white rounded-lg shadow-lg">
          <h1 className="text-3xl font-bold text-gray-900 mb-4">
            Attendance Web App
          </h1>
          <p className="text-gray-600 mb-6">
            React + TypeScript + Laravel + Inertia.js
          </p>

          <div className="p-4 bg-blue-50 border border-blue-200 rounded-md">
            <p className="text-blue-800">
              {message}
            </p>
          </div>

          <div className="mt-6">
            <p className="text-sm text-gray-500 text-center">
              Edit <code className="bg-gray-100 px-2 py-1 rounded">resources/js/Pages/Home.tsx</code> to get started
            </p>
          </div>
        </div>
      </div>
    </>
  );
}
