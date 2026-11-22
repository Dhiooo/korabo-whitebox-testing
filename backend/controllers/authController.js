const db = require('../db');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const crypto = require('crypto');

const genericResetMessage =
    'Jika data terdaftar, instruksi reset password sudah dibuat.';

function validateCredentials({ name, nim, email, password }) {
    if (name !== undefined && (typeof name !== 'string' || name.trim().length < 2 || name.trim().length > 100)) {
        return 'Nama harus terdiri dari 2-100 karakter';
    }
    if (!/^\d{5,20}$/.test(String(nim || '').trim())) {
        return 'NIM harus berupa 5-20 digit angka';
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email || '').trim())) {
        return 'Format email tidak valid';
    }
    if (typeof password !== 'string' || password.length < 4 || password.length > 72) {
        return 'Password harus terdiri dari 4-72 karakter';
    }
    return null;
}

exports.register = async (req, res) => {
    try {
        const { name, nim, email, password } = req.body || {};
        const validationError = validateCredentials({ name, nim, email, password });
        if (validationError) return res.status(400).json({ message: validationError });
        
        // Check if user exists
        const normalizedNim = nim.trim();
        const normalizedEmail = email.trim().toLowerCase();
        const [existing] = await db.execute('SELECT id FROM users WHERE nim = ? OR email = ?', [normalizedNim, normalizedEmail]);
        if (existing.length > 0) {
            return res.status(400).json({ message: 'NIM atau Email sudah terdaftar' });
        }

        const hashedPassword = await bcrypt.hash(password, 10);
        await db.execute(
            'INSERT INTO users (name, nim, email, password) VALUES (?, ?, ?, ?)',
            [name.trim(), normalizedNim, normalizedEmail, hashedPassword]
        );

        res.status(201).json({ message: 'Registrasi berhasil' });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
};

exports.login = async (req, res) => {
    try {
        const { nim, password } = req.body || {};
        if (!/^\d{5,20}$/.test(String(nim || '').trim()) || typeof password !== 'string' || password.length === 0) {
            return res.status(400).json({ message: 'NIM dan password wajib diisi dengan format yang valid' });
        }
        const [users] = await db.execute('SELECT * FROM users WHERE nim = ?', [nim.trim()]);
        
        if (users.length === 0) {
            return res.status(401).json({ message: 'NIM tidak ditemukan' });
        }

        const user = users[0];
        const isMatch = await bcrypt.compare(password, user.password);
        
        if (!isMatch) {
            return res.status(401).json({ message: 'Password salah' });
        }

        const token = jwt.sign({ id: user.id }, process.env.JWT_SECRET, { expiresIn: '1d' });
        
        res.json({
            message: 'Login berhasil',
            token,
            user: { id: user.id, name: user.name, nim: user.nim }
        });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
};

exports.requestPasswordReset = async (req, res) => {
    try {
        const { nim, email } = req.body || {};
        const normalizedNim = String(nim || '').trim();
        const normalizedEmail = String(email || '').trim().toLowerCase();

        if (!/^\d{5,20}$/.test(normalizedNim) || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(normalizedEmail)) {
            return res.status(400).json({ message: 'NIM dan email wajib diisi dengan format yang valid' });
        }

        const [users] = await db.execute(
            'SELECT id FROM users WHERE nim = ? AND email = ?',
            [normalizedNim, normalizedEmail]
        );

        // Keep the response generic so the endpoint does not reveal account existence.
        if (users.length === 0) return res.json({ message: genericResetMessage });

        const rawToken = crypto.randomBytes(32).toString('hex');
        const tokenHash = crypto.createHash('sha256').update(rawToken).digest('hex');
        await db.execute(
            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))',
            [users[0].id, tokenHash]
        );

        const response = { message: genericResetMessage };
        // The token is exposed only for local practical/demo use.
        if (process.env.NODE_ENV !== 'production') response.resetToken = rawToken;
        res.json(response);
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
};

exports.resetPassword = async (req, res) => {
    try {
        const { token, password } = req.body || {};
        if (typeof token !== 'string' || token.length < 32) {
            return res.status(400).json({ message: 'Token reset tidak valid' });
        }
        if (typeof password !== 'string' || password.length < 4 || password.length > 72) {
            return res.status(400).json({ message: 'Password harus terdiri dari 4-72 karakter' });
        }

        const tokenHash = crypto.createHash('sha256').update(token).digest('hex');
        const [rows] = await db.execute(
            `SELECT id, user_id FROM password_reset_tokens
             WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
             ORDER BY id DESC LIMIT 1`,
            [tokenHash]
        );
        if (rows.length === 0) return res.status(400).json({ message: 'Token reset tidak valid atau sudah kedaluwarsa' });

        const hashedPassword = await bcrypt.hash(password, 10);
        await db.execute('UPDATE users SET password = ? WHERE id = ?', [hashedPassword, rows[0].user_id]);
        await db.execute('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?', [rows[0].id]);

        res.json({ message: 'Password berhasil diubah. Silakan login.' });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
};
