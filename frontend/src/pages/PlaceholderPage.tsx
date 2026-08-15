export default function PlaceholderPage({ title }: { title: string }) {
  return (
    <div className="space-y-4">
      <h1 className="text-xl font-bold text-slate-900">{title}</h1>
      <div className="rounded-xl bg-white p-8 text-center shadow-sm">
        <p className="text-slate-500">
          {title} screen is ready for the next iteration.
        </p>
        <p className="mt-2 text-sm text-slate-400">
          Backend API is fully available for this module.
        </p>
      </div>
    </div>
  );
}
