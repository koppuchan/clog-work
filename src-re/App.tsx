import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import AdminLayout from './layouts/AdminLayout';
import StaffLayout from './layouts/StaffLayout';

// Admin pages
import Shifts from './pages/admin/Shifts';
import Users from './pages/admin/Users';
import UserNew from './pages/admin/UserNew';
import UserEdit from './pages/admin/UserEdit';
import Applications from './pages/admin/Applications';
import Alerts from './pages/admin/Alerts';
import Reports from './pages/admin/Reports';
import Billing from './pages/admin/Billing';
import Settings from './pages/admin/Settings';

// Staff pages
import Staff from './pages/staff/Staff';
import StaffShifts from './pages/staff/Shifts';
import StaffReports from './pages/staff/Reports';

function App() {
  return (
    <BrowserRouter>
      <Routes>
        {/* Admin routes */}
        <Route path="/admin" element={<AdminLayout><Navigate to="/admin/shifts" replace /></AdminLayout>} />
        <Route path="/admin/shifts" element={<AdminLayout><Shifts /></AdminLayout>} />
        <Route path="/admin/users" element={<AdminLayout><Users /></AdminLayout>} />
        <Route path="/admin/users/new" element={<AdminLayout><UserNew /></AdminLayout>} />
        <Route path="/admin/users/:id/edit" element={<AdminLayout><UserEdit /></AdminLayout>} />
        <Route path="/admin/applications" element={<AdminLayout><Applications /></AdminLayout>} />
        <Route path="/admin/alerts" element={<AdminLayout><Alerts /></AdminLayout>} />
        <Route path="/admin/reports" element={<AdminLayout><Reports /></AdminLayout>} />
        <Route path="/admin/billing" element={<AdminLayout><Billing /></AdminLayout>} />
        <Route path="/admin/settings" element={<AdminLayout><Settings /></AdminLayout>} />

        {/* Staff routes */}
        <Route path="/staff" element={<StaffLayout><Staff /></StaffLayout>} />
        <Route path="/staff/shifts" element={<StaffLayout><StaffShifts /></StaffLayout>} />
        <Route path="/staff/reports" element={<StaffLayout><StaffReports /></StaffLayout>} />

        {/* Default redirect */}
        <Route path="/" element={<Navigate to="/admin/shifts" replace />} />
      </Routes>
    </BrowserRouter>
  );
}

export default App;
