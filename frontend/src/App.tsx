import { Routes, Route } from 'react-router-dom';

function App() {
  return (
    <div className="min-h-screen bg-slate-50 text-slate-900">
      <Routes>
        <Route
          path="/"
          element={
            <div className="flex min-h-screen flex-col items-center justify-center p-6">
              <div className="w-full max-w-md rounded-2xl bg-white p-8 shadow-lg">
                <div className="mb-6 text-center">
                  <h1 className="text-3xl font-bold tracking-tight text-teal-700">
                    MyStocks
                  </h1>
                  <p className="mt-2 text-sm text-slate-500">
                    by CNMG Technologies
                  </p>
                </div>
                <p className="text-center text-slate-600">
                  Production foundation is being built.
                  <br />
                  Phase 1 complete. Authentication &amp; core modules coming next.
                </p>
                <div className="mt-8 rounded-lg bg-teal-50 p-4 text-center text-sm text-teal-800">
                  Offline-capable • Multi-tenant • Ghana-first
                </div>
              </div>
            </div>
          }
        />
      </Routes>
    </div>
  );
}

export default App;
