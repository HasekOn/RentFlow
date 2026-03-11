import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { AuthProvider } from './contexts/AuthContext'
import ProtectedRoute from './components/layout/ProtectedRoute'
import AppLayout from './components/layout/AppLayout'
import LoginPage from './pages/auth/LoginPage'
import RegisterPage from './pages/auth/RegisterPage'
import DashboardPage from './pages/dashboard/DashboardPage'
import PropertiesPage from './pages/properties/PropertiesPage'
import PropertyDetailPage from './pages/properties/PropertyDetailPage'
import TicketsPage from './pages/tickets/TicketsPage'
import TicketDetailPage from './pages/tickets/TicketDetailPage'
import LeasesPage from './pages/leases/LeasesPage'
import LeaseDetailPage from './pages/leases/LeaseDetailPage'
import PaymentsPage from './pages/payments/PaymentsPage'
import PeoplePage from './pages/people/PeoplePage'
import PersonDetailPage from './pages/people/PersonDetailPage'
import DocumentsPage from './pages/documents/DocumentsPage'
import SettingsPage from './pages/settings/SettingsPage'

export default function App() {
    return (
        <BrowserRouter>
            <AuthProvider>
                <Routes>
                    {/* Public */}
                    <Route path="/login" element={<LoginPage />} />
                    <Route path="/register" element={<RegisterPage />} />

                    {/* Protected */}
                    <Route
                        element={
                            <ProtectedRoute>
                                <AppLayout />
                            </ProtectedRoute>
                        }
                    >
                        <Route path="/" element={<DashboardPage />} />
                        <Route path="/properties" element={<PropertiesPage />} />
                        <Route path="/properties/:id" element={<PropertyDetailPage />} />
                        <Route path="/tickets" element={<TicketsPage />} />
                        <Route path="/tickets/:id" element={<TicketDetailPage />} />
                        <Route path="/leases" element={<LeasesPage />} />
                        <Route path="/leases/:id" element={<LeaseDetailPage />} />
                        <Route path="/payments" element={<PaymentsPage />} />
                        <Route path="/people" element={<PeoplePage />} />
                        <Route path="/people/:id" element={<PersonDetailPage />} />
                        <Route path="/documents" element={<DocumentsPage />} />
                        <Route path="/settings" element={<SettingsPage />} />
                    </Route>

                    {/* Fallback */}
                    <Route path="*" element={<Navigate to="/" replace />} />
                </Routes>
            </AuthProvider>
        </BrowserRouter>
    )
}
